<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    public const STATUSES = ["pending", "approved", "rejected"];

    protected $fillable = [
        "employee_id", "leave_date", "hours", "reason",
        "status", "reviewed_by", "reviewed_at",
    ];

    protected function casts(): array
    {
        return [
            "leave_date" => "date",
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

    public function approve(User $reviewer): void
    {
        $this->update(["status" => "approved", "reviewed_by" => $reviewer->id, "reviewed_at" => now()]);
    }

    public function reject(User $reviewer): void
    {
        $this->update(["status" => "rejected", "reviewed_by" => $reviewer->id, "reviewed_at" => now()]);
    }
}
