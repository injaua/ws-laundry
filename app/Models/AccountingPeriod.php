<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class AccountingPeriod extends Model
{
    use BelongsToStore;
    use Loggable;

    public const STATUSES = ["closed", "reopened"];

    protected $fillable = ["year", "month", "status", "closed_by", "closed_at", "reopened_by", "reopened_at", "notes"];

    protected function casts(): array
    {
        return [
            "year" => "integer",
            "month" => "integer",
            "closed_at" => "datetime",
            "reopened_at" => "datetime",
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, "closed_by");
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, "reopened_by");
    }

    public function archive(): HasOne
    {
        return $this->hasOne(TrialBalanceArchive::class);
    }

    public function isClosed(): bool
    {
        return $this->status === "closed";
    }

    public function label(): string
    {
        return sprintf("%02d/%04d", $this->month, $this->year);
    }

    public function monthStart(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->startOfMonth();
    }

    public function monthEnd(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->endOfMonth();
    }

    public static function forDate(Carbon|string $date): ?self
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return static::where("year", $date->year)->where("month", $date->month)->first();
    }

    public static function closedOn(Carbon|string $date): ?self
    {
        $period = static::forDate($date);

        return $period && $period->isClosed() ? $period : null;
    }

    /**
     * Throw a field validation error when $date falls in a closed period.
     */
    public static function assertOpen(Carbon|string $date, string $field = "date"): void
    {
        $period = static::closedOn($date);
        if (!$period) {
            return;
        }

        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        throw ValidationException::withMessages([
            $field => __("app.period_closed", ["period" => sprintf("%02d/%04d", $date->month, $date->year)]),
        ]);
    }
}
