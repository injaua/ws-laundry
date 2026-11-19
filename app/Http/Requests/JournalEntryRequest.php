<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAmounts;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * A manual journal entry: accounting.journal-entries.store and .update.
 *
 * The entry is typed as rows of debit/credit pairs. Blank rows are dropped; every
 * remaining row needs two different manual-use accounts and two amounts of at least
 * 1 MT, and the entry must balance to the cent on the amounts actually stored. Row
 * problems are reported on "pairs", where the form shows them.
 */
class JournalEntryRequest extends FormRequest
{
    use ValidatesAmounts;

    /** Most rows one entry may carry. */
    private const MAX_ROWS = 100;

    /** @var array<int, array{debit_account_id: int, debit_amount: float, credit_account_id: int, credit_amount: float}> */
    private array $pairs = [];

    public function authorize(): bool
    {
        $entry = $this->entry();

        return $entry ? Gate::allows("update", $entry) : Gate::allows("create", JournalEntry::class);
    }

    /** Rows with nothing typed in them are not part of the entry. */
    protected function prepareForValidation(): void
    {
        $pairs = $this->input("pairs", []);

        $this->merge([
            "pairs" => collect(is_array($pairs) ? $pairs : [])
                ->filter(fn ($row) => is_array($row) && collect($row)->contains(fn ($v) => trim((string) $v) !== ""))
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            "entry_date" => ["required", "date"],
            "description" => ["required", "string", "max:255"],
            "pairs" => ["array", "max:" . self::MAX_ROWS],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Editing touches the period the entry was in and the one it moves to.
                if ($entry = $this->entry()) {
                    $this->rejectClosedPeriod($validator, $entry->entry_date, "entry_date");
                }
                $this->rejectClosedPeriod($validator, $this->input("entry_date"), "entry_date");

                if (!$validator->errors()->has("pairs") && ($error = $this->pairsError()) !== null) {
                    $validator->errors()->add("pairs", $error);
                }
            },
        ];
    }

    /** Header fields of the entry. */
    public function entryData(): array
    {
        return $this->safe()->only(["entry_date", "description"]);
    }

    /**
     * The validated rows, amounts rounded to cents.
     *
     * @return array<int, array{debit_account_id: int, debit_amount: float, credit_account_id: int, credit_amount: float}>
     */
    public function pairs(): array
    {
        return $this->pairs;
    }

    /** The entry being edited, null when posting a new one. */
    public function entry(): ?JournalEntry
    {
        $entry = $this->route("journal_entry");

        return $entry instanceof JournalEntry ? $entry : null;
    }

    /** First problem found in the rows, or null when the entry is valid and balanced. */
    private function pairsError(): ?string
    {
        $rows = $this->input("pairs", []);
        if ($rows === []) {
            return __("app.journal_rows_required");
        }

        $accounts = Account::whereIn("id", collect($rows)
            ->flatMap(fn ($row) => [$row["debit_account_id"] ?? null, $row["credit_account_id"] ?? null])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique())
            ->get()
            ->keyBy("id");

        $pairs = [];
        foreach ($rows as $i => $row) {
            $line = ["row" => $i + 1];
            $debitAccount = $this->account($accounts, $row["debit_account_id"] ?? null);
            $creditAccount = $this->account($accounts, $row["credit_account_id"] ?? null);

            if (!$debitAccount?->isManualUsable() || !$creditAccount?->isManualUsable()) {
                return __("app.journal_account_invalid", $line);
            }
            if (!$this->isAmount($row["debit_amount"] ?? null) || !$this->isAmount($row["credit_amount"] ?? null)) {
                return __("app.journal_amount_invalid", $line);
            }
            if ($debitAccount->id === $creditAccount->id) {
                return __("app.journal_same_account", $line);
            }

            // Rounded to cents first: the balance is checked on the amounts actually stored.
            $pairs[] = [
                "debit_account_id" => $debitAccount->id,
                "debit_amount" => Money::round($row["debit_amount"]),
                "credit_account_id" => $creditAccount->id,
                "credit_amount" => Money::round($row["credit_amount"]),
            ];
        }

        if (!Money::equals(Money::sum(array_column($pairs, "debit_amount")), Money::sum(array_column($pairs, "credit_amount")))) {
            return __("app.journal_not_balanced");
        }

        $this->pairs = $pairs;

        return null;
    }

    private function account($accounts, mixed $id): ?Account
    {
        return is_numeric($id) ? $accounts->get((int) $id) : null;
    }

    /** A number of at least 1 MT that fits the amount column. */
    private function isAmount(mixed $value): bool
    {
        return is_numeric($value)
            && Money::compare($value, 1) >= 0
            && Money::compare($value, self::MAX_AMOUNT) <= 0;
    }
}
