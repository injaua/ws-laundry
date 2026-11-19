<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrialBalanceArchive extends Model
{
    use BelongsToStore;
    use Loggable;

    protected $fillable = ["accounting_period_id", "year", "month", "from_date", "to_date", "total_debit", "total_credit", "created_by"];

    protected function casts(): array
    {
        return [
            "year" => "integer",
            "month" => "integer",
            "from_date" => "date",
            "to_date" => "date",
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, "accounting_period_id");
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TrialBalanceArchiveLine::class)->orderBy("account_code");
    }

    public function label(): string
    {
        return sprintf("%02d/%04d", $this->month, $this->year);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where("year", $year);
    }
}
