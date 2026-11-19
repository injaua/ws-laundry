<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseCategory extends Model
{
    use BelongsToStore;

    public const COLORS = ["secondary", "success", "danger", "warning", "info", "primary", "dark"];

    protected $fillable = ["key", "name", "color", "is_fixed", "default_amount", "is_salary", "is_vat_applicable", "account_id"];

    protected function casts(): array
    {
        return [
            "is_fixed" => "boolean",
            "is_salary" => "boolean",
            "is_vat_applicable" => "boolean",
            "default_amount" => "decimal:0",
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public static function salary(): ?self
    {
        return static::where("is_salary", true)->first();
    }

    public function expenseCount(): int
    {
        return Expense::where("category", $this->key)->count();
    }
}
