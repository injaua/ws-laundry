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

    protected $fillable = ["title", "description", "category", "period_type", "amount", "vat_rate", "expense_date", "user_id", "source_type", "source_id"];

    protected static function booted(): void
    {
        // The author decides who may change the expense (ExpensePolicy).
        static::creating(function (Expense $expense) {
            $expense->user_id ??= auth()->id();
        });

        // amount is gross: keep the VAT part it contains in sync with the rate.
        static::saving(function (Expense $expense) {
            $rate = $expense->effectiveVatRate();
            $expense->vat_amount = $rate > 0 ? Money::splitGross(Money::round($expense->amount), $rate)[1] : 0;
        });
    }

    protected function casts(): array
    {
        return [
            "amount" => "decimal:2",
            "vat_rate" => "decimal:2",
            "vat_amount" => "decimal:2",
            "expense_date" => "date",
        ];
    }

    /** The store's VAT rate when the category carries VAT, else 0 — the form's suggestion. */
    public static function defaultVatRate(?ExpenseCategory $category): float
    {
        return $category?->is_vat_applicable ? (float) SiteSetting::get("vat_rate", 16) : 0.0;
    }

    /**
     * The VAT rate charged on the purchase, as entered on the expense. Expenses recorded
     * without one (older rows, automatic expenses) follow their category's rule.
     */
    public function effectiveVatRate(): float
    {
        if ($this->vat_rate !== null) {
            return (float) $this->vat_rate;
        }

        return self::defaultVatRate(ExpenseCategory::firstWhere("key", $this->category));
    }

    /** Net amount (gross minus the VAT charged). */
    public function netAmount(): float
    {
        return Money::sub($this->amount, $this->vat_amount);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Debit the category's linked expense account, credit Caixa (1.1).
     * The amount is gross: when the purchase carried VAT it is split into the net
     * expense and deductible VAT (4.4.3.2 IVA dedutível) at the expense's own rate,
     * which the VAT settlement offsets against output VAT to get the VAT payable.
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

        $rate = $this->effectiveVatRate();
        $vatAccount = $rate > 0 ? (Account::findByCode("4.4.3.2") ?? Account::findByCode("4.4.3.1"))?->id : null;

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
