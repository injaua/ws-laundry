<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Support\CurrentStore;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VatSettlementController extends Controller
{
    public function index(Request $request)
    {
        [$month, $year] = $this->period($request);
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $outputVat = $this->balance("4.4.3.3", $end, creditNatural: true);
        $inputVat = $this->balance("4.4.3.1", $end, creditNatural: false);
        $netVat = round($outputVat - $inputVat, 2);

        $movements = DB::table("journal_lines")
            ->join("journal_entries", "journal_entries.id", "=", "journal_lines.journal_entry_id")
            ->where("journal_entries.store_id", CurrentStore::id())
            ->join("accounts", "accounts.id", "=", "journal_lines.account_id")
            ->whereIn("accounts.code", ["4.4.3.3", "4.4.3.1"])
            ->where("journal_entries.entry_date", "<=", $end->copy()->endOfDay()->toDateTimeString())
            ->orderByDesc("journal_entries.entry_date")
            ->orderByDesc("journal_entries.id")
            ->select("accounts.code", "accounts.name", "journal_entries.entry_number", "journal_entries.description", "journal_entries.entry_date", "journal_lines.debit", "journal_lines.credit")
            ->get();

        return view("accounting.vat-settlement", compact("month", "year", "outputVat", "inputVat", "netVat", "movements"));
    }

    public function store(Request $request)
    {
        [$month, $year] = $this->period($request, fromPost: true);
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        if ($closed = AccountingPeriod::closedOn($end)) {
            return redirect()->route("accounting.vat-settlement", ["month" => $month, "year" => $year])
                ->with("error", __("app.period_closed", ["period" => $closed->label()]));
        }

        $outputVat = $this->balance("4.4.3.3", $end, creditNatural: true);
        $inputVat = $this->balance("4.4.3.1", $end, creditNatural: false);

        if (abs($outputVat) < 0.005 && abs($inputVat) < 0.005) {
            return redirect()->route("accounting.vat-settlement", ["month" => $month, "year" => $year])
                ->with("error", __("app.vat_settlement_nothing"));
        }

        $difference = round($outputVat - $inputVat, 2);
        $accounts = [
            "4.4.3.3" => Account::findByCode("4.4.3.3"),
            "4.4.3.1" => Account::findByCode("4.4.3.1"),
            "4.4.3.7" => Account::findByCode("4.4.3.7"),
        ];

        if (abs($difference) >= 0.005 && !$accounts["4.4.3.7"]) {
            return redirect()->route("accounting.vat-settlement", ["month" => $month, "year" => $year])
                ->with("error", __("app.vat_settlement_missing_account"));
        }

        $lines = [];
        $this->addLine($lines, $accounts["4.4.3.3"], $outputVat, debitNatural: true);
        $this->addLine($lines, $accounts["4.4.3.1"], $inputVat, debitNatural: false);
        $this->addLine($lines, $accounts["4.4.3.7"], $difference, debitNatural: false);

        if ($lines === []) {
            return redirect()->route("accounting.vat-settlement", ["month" => $month, "year" => $year])
                ->with("error", __("app.vat_settlement_nothing"));
        }

        $entry = JournalEntry::create([
            "entry_number" => "TMP-" . Str::upper(Str::random(12)),
            "entry_date" => $end->toDateString(),
            "description" => __("app.vat_settlement") . " — " . sprintf("%02d/%04d", $month, $year),
            "source" => "manual",
            "posted_by" => auth()->id(),
        ]);
        $entry->update(["entry_number" => JournalEntry::number($entry->id)]);

        foreach ($lines as $line) {
            $entry->lines()->create($line);
        }

        return redirect()->route("accounting.vat-settlement", ["month" => $month, "year" => $year])
            ->with("success", __("app.vat_settlement_created", ["number" => $entry->entry_number]));
    }

    private function period(Request $request, bool $fromPost = false): array
    {
        $input = $fromPost ? $request->input() : $request->query();
        $month = min(12, max(1, (int) ($input["month"] ?? now()->month)));
        $year = min(2100, max(2000, (int) ($input["year"] ?? now()->year)));

        return [$month, $year];
    }

    private function balance(string $code, Carbon $end, bool $creditNatural): float
    {
        $sums = DB::table("journal_lines")
            ->join("journal_entries", "journal_entries.id", "=", "journal_lines.journal_entry_id")
            ->where("journal_entries.store_id", CurrentStore::id())
            ->join("accounts", "accounts.id", "=", "journal_lines.account_id")
            ->where("accounts.code", $code)
            ->where("journal_entries.entry_date", "<=", $end->copy()->endOfDay()->toDateTimeString())
            ->select(DB::raw("COALESCE(SUM(journal_lines.debit),0) as d"), DB::raw("COALESCE(SUM(journal_lines.credit),0) as c"))
            ->first();

        $difference = (float) $sums->c - (float) $sums->d;

        return round($creditNatural ? $difference : -$difference, 2);
    }

    /**
     * $amount > 0 posts on the natural side (debit when debitNatural), < 0 on the other.
     */
    private function addLine(array &$lines, ?Account $account, float $amount, bool $debitNatural): void
    {
        if (!$account || abs($amount) < 0.005) {
            return;
        }

        $onDebitSide = ($amount > 0) === $debitNatural;
        $lines[] = [
            "account_id" => $account->id,
            "debit" => $onDebitSide ? abs($amount) : 0,
            "credit" => $onDebitSide ? 0 : abs($amount),
        ];
    }
}
