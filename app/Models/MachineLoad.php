<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One machine cycle (wash or dry) that may mix several orders. It links each order
 * to the machine and the operator, counts the machine's cycles and consumes the
 * machine's per-cycle recipe.
 */
class MachineLoad extends Model
{
    use BelongsToStore;
    use Loggable;

    public const STAGES = ["washing", "drying"];

    public const STATUSES = ["in_progress", "done", "cancelled"];

    /** Machine type that runs each stage. */
    public const MACHINE_TYPES = ["washing" => "washer", "drying" => "dryer"];

    protected $fillable = [
        "code", "equipment_id", "stage", "program", "weight_kg", "employee_id", "user_id",
        "status", "started_at", "finished_at", "overload_note", "notes",
    ];

    protected function casts(): array
    {
        return [
            "weight_kg" => "decimal:2",
            "started_at" => "datetime",
            "finished_at" => "datetime",
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, "machine_load_order")->withPivot("weight_kg");
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where("status", "in_progress");
    }

    public function statusColor(): string
    {
        return ["in_progress" => "primary", "done" => "success", "cancelled" => "secondary"][$this->status] ?? "secondary";
    }

    /** Share of the machine capacity used (null when the capacity is unknown). */
    public function utilization(): ?float
    {
        $capacity = (float) ($this->equipment?->capacity_kg ?? 0);

        return $capacity > 0 ? round((float) $this->weight_kg / $capacity * 100, 1) : null;
    }

    public function durationMinutes(): ?int
    {
        return $this->started_at && $this->finished_at ? (int) $this->started_at->diffInMinutes($this->finished_at) : null;
    }

    public static function nextCode(): string
    {
        $prefix = "CG-" . now()->format("ymd") . "-";
        $last = static::where("code", "like", $prefix . "%")->orderByDesc("code")->value("code");
        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 3, "0", STR_PAD_LEFT);
    }
}
