<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceJustification extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    public const STATUSES = ["pending", "approved", "rejected"];

    protected $fillable = [
        "employee_id", "absence_date", "reason",
        "status", "reviewed_by", "reviewed_at", "notes",
    ];

    protected function casts(): array
    {
        return [
            "absence_date" => "date:Y-m-d",
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

    /**
     * Approving converts the day's unjustified absence into an excused record.
     */
    public function approve(User $reviewer): void
    {
        $this->update(["status" => "approved", "reviewed_by" => $reviewer->id, "reviewed_at" => now()]);

        $attendance = Attendance::where("employee_id", $this->employee_id)
            ->where("attendance_date", $this->absence_date->toDateString())
            ->where("status", "absent")
            ->first();
        $attendance?->update(["status" => "excused", "notes" => $this->reason]);
    }

    public function reject(User $reviewer): void
    {
        $this->update(["status" => "rejected", "reviewed_by" => $reviewer->id, "reviewed_at" => now()]);
    }
}
