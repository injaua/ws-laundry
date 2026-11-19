<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToStore;
    use HasFactory;
    use Loggable;

    protected $fillable = ["title", "description", "category", "period_type", "amount", "expense_date", "user_id", "source_type", "source_id"];

    protected static function booted(): void
    {
        // The author decides who may change the expense (ExpensePolicy).
        static::creating(function (Expense $expense) {
            $expense->user_id ??= auth()->id();
        });
    }

    protected function casts(): array
    {
        return [
            "amount" => "decimal:2",
            "expense_date" => "date",
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Debit the category's linked expense account, credit Caixa (1.1).
     * For VAT-applicable categories the amount is gross: it is split into the
     * net expense and input VAT (4.4.3.1) at the rate configured today — the
     * rate is intentionally not snapshotted on the expense.
     * Categories without an account produce no entry.
     */
    public function postToJournal(): ?JournalEntry
    {
        $category = ExpenseCategory::firstWhere("key", $this->category);

        if (!$category?->account_id) {
            return null;
        }

        $date = $this->expense_date->toDateString();
        $amount = (float) $this->amount;
        $cashId = Account::findByCode("1.1")?->id;

        $rate = $category->is_vat_applicable ? (float) SiteSetting::get("vat_rate", 16) : 0.0;
        $vatAccount = $rate > 0 ? Account::findByCode("4.4.3.1")?->id : null;

        if ($vatAccount && $cashId) {
            // Rounded to cents and clamped so net + VAT always re-sum to the gross.
            $total = Money::round($amount);
            [$net, $vat] = Money::splitGross($total, $rate);

            return JournalEntry::postLines([
                ["account_id" => $category->account_id, "debit" => $net, "credit" => 0],
                ["account_id" => $vatAccount, "debit" => $vat, "credit" => 0],
                ["account_id" => $cashId, "debit" => 0, "credit" => $total],
            ], $date, $this->title, "expense", $this->id);
        }

        return JournalEntry::postPair(
            $category->account_id,
            $cashId,
            $amount,
            $date,
            $this->title,
            "expense",
            $this->id,
        );
    }
}
