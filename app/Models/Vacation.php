<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vacation extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    public const STATUSES = ["pending", "approved", "rejected"];

    protected $fillable = [
        "employee_id", "start_date", "end_date", "days", "type",
        "status", "reviewed_by", "reviewed_at", "notes",
    ];

    protected function casts(): array
    {
        return [
            "start_date" => "date",
            "end_date" => "date",
            "reviewed_at" => "datetime",
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, "reviewed_by");
    }

    public static function workingDays(string $start, string $end): int
    {
        $days = 0;
        $current = Carbon::parse($start);
        $last = Carbon::parse($end);

        while ($current->lte($last)) {
            if (!$current->isSunday()) {
                $days++;
            }
            $current = $current->copy()->addDay();
        }

        return $days;
    }

    public function approve(User $reviewer): void
    {
        $this->update(["status" => "approved", "reviewed_by" => $reviewer->id, "reviewed_at" => now()]);
    }

    public function reject(User $reviewer): void
    {
        $this->update(["status" => "rejected", "reviewed_by" => $reviewer->id, "reviewed_at" => now()]);
    }

    public function coversDate(string $date): bool
    {
        $day = Carbon::parse($date);

        return $this->status === "approved"
            && $this->start_date->lte($day)
            && $this->end_date->gte($day);
    }
}
