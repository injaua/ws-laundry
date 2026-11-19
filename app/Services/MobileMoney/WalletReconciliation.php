<?php

namespace App\Services\MobileMoney;

use App\Models\BankDeposit;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletStatement;
use App\Models\WalletStatementLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Wallet statement import: each incoming movement is matched with the payments
 * recorded with the same operator reference. Lines without a payment are money in
 * the wallet that nobody recorded; payments without a line (see missingPayments)
 * were recorded but never reached the wallet.
 */
class WalletReconciliation
{
    /** Statements that can be imported: the bank account (transfers + deposits) and the wallets. */
    public const PROVIDERS = ["transfer", "mpesa", "emola", "mkesh"];

    /** Column names recognised in the operators' exports (lower case, no accents). */
    public const COLUMN_GUESSES = [
        "ref" => ["transaction id", "id da transacao", "id transacao", "transacao", "receipt", "receipt no", "referencia", "reference", "ref", "trans id", "id", "descricao", "descritivo", "description", "narrativa", "detalhes", "documento", "movimento"],
        "amount" => ["amount", "valor", "montante", "credito", "credit", "paid in", "entrada", "quantia"],
        "date" => ["date", "data", "completion time", "data/hora", "data e hora", "datetime", "hora", "initiation time"],
        "phone" => ["msisdn", "telefone", "numero", "phone", "origem", "from", "other party", "contraparte", "remetente"],
    ];

    /**
     * @param  array<string, int|string|null>  $mapping  field => column index (optional)
     */
    public function import(UploadedFile $file, string $provider, User $user, array $mapping = []): WalletStatement
    {
        $rows = $this->readRows($file);
        if (count($rows) < 2) {
            throw ValidationException::withMessages(["file" => __("app.statement_empty")]);
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($rows));
        $columns = $this->columns($headers, $mapping);

        if ($columns["ref"] === null || $columns["amount"] === null) {
            throw ValidationException::withMessages(["file" => __("app.statement_columns_not_found", ["columns" => implode(", ", array_filter($headers))])]);
        }

        $lines = collect($rows)->map(function ($row) use ($columns, $provider) {
            $ref = Payment::normalizeRef($row[$columns["ref"]] ?? null);
            $amount = $this->number($row[$columns["amount"]] ?? null);
            if (!$ref || $amount === null || $amount <= 0) {
                return null; // outgoing movements, fees, totals
            }

            return [
                "provider" => $provider,
                "transaction_ref" => mb_substr($ref, 0, 60),
                "amount" => round($amount, 2),
                "occurred_at" => $columns["date"] !== null ? $this->date($row[$columns["date"]] ?? null) : null,
                "phone" => $columns["phone"] !== null ? mb_substr(preg_replace("/[^0-9+]/", "", (string) ($row[$columns["phone"]] ?? "")), 0, 20) ?: null : null,
                "description" => mb_substr(implode(" | ", array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : "", $row))), 0, 255),
            ];
        })->filter()->values();

        // Lines already imported from an earlier statement are skipped. Bank descriptions
        // repeat ("TRF ..."), so a line is the same one when reference, amount and day match.
        $key = fn ($ref, $amount, $date) => $ref . "|" . number_format((float) $amount, 2, ".", "") . "|" . ($date ? Carbon::parse($date)->toDateString() : "");
        $known = WalletStatementLine::where("provider", $provider)->whereIn("transaction_ref", $lines->pluck("transaction_ref"))->get()
            ->map(fn ($l) => $key($l->transaction_ref, $l->amount, $l->occurred_at))->all();
        $lines = $lines->reject(fn ($l) => in_array($key($l["transaction_ref"], $l["amount"], $l["occurred_at"]), $known, true))
            ->unique(fn ($l) => $key($l["transaction_ref"], $l["amount"], $l["occurred_at"]))->values();

        return DB::transaction(function () use ($lines, $provider, $file, $user) {
            $dates = $lines->pluck("occurred_at")->filter();
            $statement = WalletStatement::create([
                "provider" => $provider,
                "file_name" => mb_substr($file->getClientOriginalName(), 0, 200),
                "period_from" => $dates->min()?->toDateString(),
                "period_to" => $dates->max()?->toDateString(),
                "lines_count" => $lines->count(),
                "user_id" => $user->id,
            ]);

            $matched = 0;
            foreach ($lines as $line) {
                $payments = Payment::where("method", $provider)->where("transaction_ref", $line["transaction_ref"])->get();
                $note = null;
                $deposit = null;

                // Bank statement: the reference is usually inside the description, and cash /
                // cheque deposits appear as one line per deposit slip.
                if ($provider === "transfer" && $payments->isEmpty()) {
                    [$payments, $deposit, $note] = $this->matchBankLine($line);
                }

                if ($deposit) {
                    $statement->lines()->create($line + ["status" => "matched", "recorded_amount" => (float) $deposit->total, "match_note" => $note]);
                    $deposit->update(["confirmed_at" => now()]);
                    $matched++;

                    continue;
                }

                $recorded = (float) $payments->sum("amount");
                $status = $payments->isEmpty() ? "unmatched" : (abs($recorded - $line["amount"]) <= 1 ? "matched" : "amount_mismatch");
                if (!$note && $payments->isNotEmpty() && $provider === "transfer") {
                    $note = __("app.match_by_reference", ["ref" => $payments->first()->transaction_ref]);
                }

                $statement->lines()->create($line + ["status" => $status, "recorded_amount" => $payments->isEmpty() ? null : $recorded, "match_note" => $note]);

                if ($status === "matched") {
                    $matched++;
                    Payment::whereIn("id", $payments->pluck("id"))->update(["reconciled_at" => now()]);
                }
            }

            $statement->update(["matched_count" => $matched]);

            return $statement;
        });
    }

    /**
     * Bank line without an exact reference:
     * 1. a recorded transfer reference contained in the description;
     * 2. an unconfirmed bank deposit (slip number in the description, or same total within 5 days);
     * 3. a single unreconciled transfer with the same amount within 3 days.
     *
     * @return array{0: Collection, 1: ?BankDeposit, 2: ?string}
     */
    private function matchBankLine(array $line): array
    {
        $date = $line["occurred_at"] ? Carbon::parse($line["occurred_at"]) : null;
        $haystack = (string) Payment::normalizeRef($line["transaction_ref"] . " " . ($line["description"] ?? ""));
        $window = fn ($q, int $before, int $after) => $date
            ? $q->whereDate("paid_at", ">=", $date->copy()->subDays($before)->toDateString())->whereDate("paid_at", "<=", $date->copy()->addDays($after)->toDateString())
            : $q;

        // 1. reference written inside the description
        $candidates = $window(Payment::where("method", "transfer")->whereNull("reconciled_at")->whereNotNull("transaction_ref"), 15, 2)->get()
            ->filter(fn ($p) => strlen($p->transaction_ref) >= 4 && str_contains($haystack, $p->transaction_ref))
            ->groupBy("transaction_ref");
        if ($candidates->count() === 1) {
            return [$candidates->first(), null, null];
        }

        // 2. bank deposit
        $deposits = BankDeposit::whereNull("confirmed_at")
            ->when($date, fn ($q) => $q->whereDate("deposited_at", ">=", $date->copy()->subDays(5)->toDateString())->whereDate("deposited_at", "<=", $date->toDateString()))
            ->get();
        $deposit = $deposits->first(fn ($d) => $d->slip_number && strlen($d->slip_number) >= 4 && str_contains($haystack, (string) Payment::normalizeRef($d->slip_number)))
            ?? (($same = $deposits->filter(fn ($d) => abs((float) $d->total - $line["amount"]) <= 1))->count() === 1 ? $same->first() : null);
        if ($deposit) {
            return [collect(), $deposit, __("app.match_by_deposit", ["deposit" => $deposit->label()])];
        }

        // 3. amount and date (only when there is exactly one candidate)
        if ($date) {
            $sameAmount = $window(Payment::where("method", "transfer")->whereNull("reconciled_at"), 3, 3)->get()
                ->groupBy(fn ($p) => $p->group_ref ?? "p" . $p->id)
                ->filter(fn ($group) => abs((float) $group->sum("amount") - $line["amount"]) <= 1);
            if ($sameAmount->count() === 1) {
                return [$sameAmount->first(), null, __("app.match_by_amount")];
            }
        }

        return [collect(), null, null];
    }

    /** Recorded mobile payments of the statement period that the wallet never received. */
    public function missingPayments(WalletStatement $statement): Collection
    {
        if (!$statement->period_from || !$statement->period_to) {
            return collect();
        }

        return Payment::with("order:id,order_number,customer_id", "order.customer:id,name", "user:id,name")
            ->where("method", $statement->provider)
            ->whereNull("reconciled_at")
            ->whereDate("paid_at", ">=", $statement->period_from->toDateString())
            ->whereDate("paid_at", "<=", $statement->period_to->toDateString())
            ->orderBy("paid_at")
            ->get();
    }

    /** First row of the file, for the column mapping form. */
    public function headers(UploadedFile $file): array
    {
        return array_map(fn ($h) => trim((string) $h), $this->readRows($file)[0] ?? []);
    }

    private function columns(array $headers, array $mapping): array
    {
        $normalized = array_map(fn ($h) => $this->plain($h), $headers);
        $columns = [];

        foreach (array_keys(self::COLUMN_GUESSES) as $field) {
            if (isset($mapping[$field]) && $mapping[$field] !== "" && isset($headers[(int) $mapping[$field]])) {
                $columns[$field] = (int) $mapping[$field];

                continue;
            }

            $columns[$field] = null;
            foreach (self::COLUMN_GUESSES[$field] as $guess) {
                $index = array_search($guess, $normalized, true);
                if ($index !== false) {
                    $columns[$field] = $index;
                    break;
                }
            }
        }

        return $columns;
    }

    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ["xlsx", "xls"], true)) {
            $sheets = Excel::toArray(new class implements ToArray
            {
                public function array(array $array): void {}
            }, $file);

            return array_values(array_filter($sheets[0] ?? [], fn ($r) => array_filter($r, fn ($v) => $v !== null && $v !== "")));
        }

        $content = file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', "", (string) $content);
        if (!mb_check_encoding($content, "UTF-8")) {
            $content = mb_convert_encoding($content, "UTF-8", "Windows-1252");
        }

        $lines = preg_split("/\r\n|\n|\r/", $content);
        $first = $lines[0] ?? "";
        $delimiter = collect([";", ",", "\t"])->sortByDesc(fn ($d) => substr_count($first, $d))->first();

        return array_values(array_filter(array_map(fn ($l) => str_getcsv($l, $delimiter), $lines), fn ($r) => count(array_filter($r, fn ($v) => trim((string) $v) !== "")) > 0));
    }

    private function plain(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, ["á" => "a", "à" => "a", "â" => "a", "ã" => "a", "é" => "e", "ê" => "e", "í" => "i", "ó" => "o", "ô" => "o", "õ" => "o", "ú" => "u", "ç" => "c", "º" => "", "." => ""]);
    }

    /** "1.234,50", "1,234.50", "1234.5", "MZN 1 234,00" -> float */
    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = preg_replace("/[^0-9,.\-]/", "", (string) $value);
        if ($value === "" || $value === "-") {
            return null;
        }

        $lastComma = strrpos($value, ",");
        $lastDot = strrpos($value, ".");
        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $value = str_replace(".", "", $value);
            $value = str_replace(",", ".", $value);
        } else {
            $value = str_replace(",", "", $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === "") {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value); // Excel serial date
        }

        foreach (["d/m/Y H:i:s", "d/m/Y H:i", "d/m/Y", "d-m-Y H:i:s", "d-m-Y", "Y-m-d H:i:s", "Y-m-d"] as $format) {
            try {
                $date = Carbon::createFromFormat($format, trim((string) $value));
                if ($date !== false) {
                    return $date;
                }
            } catch (Throwable) {
            }
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
