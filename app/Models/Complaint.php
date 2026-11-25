<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Reclamação de cliente: registered, followed up within a deadline and resolved.
 *
 * @property Carbon|null $due_at
 * @property Carbon|null $resolved_at
 */
class Complaint extends Model
{
    use BelongsToStore;

    public const CHANNELS = ["counter", "phone", "whatsapp", "email", "other"];

    public const CATEGORIES = ["stain", "damage", "lost_item", "wrong_item", "delay", "service", "billing", "other"];

    public const STATUSES = ["open", "in_progress", "resolved", "rejected"];

    public const RESOLUTIONS = ["rewash", "credit_note", "repair", "replacement", "explanation", "rejected"];

    /** Working target to answer a complaint. */
    public const DEADLINE_DAYS = 3;

    protected $fillable = [
        "number", "customer_id", "order_id", "order_piece_id", "channel", "category", "stage", "machine_load_id", "employee_id", "description",
        "status", "due_at", "resolution", "resolution_notes", "opened_by", "resolved_by", "resolved_at",
    ];

    protected function casts(): array
    {
        return ["due_at" => "date", "resolved_at" => "datetime"];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(OrderPiece::class, "order_piece_id");
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, "opened_by");
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, "resolved_by");
    }

    public function machineLoad(): BelongsTo
    {
        return $this->belongsTo(MachineLoad::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(OrderRework::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn("status", ["open", "in_progress"]);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ["resolved", "rejected"], true);
    }

    public function isLate(): bool
    {
        return !$this->isClosed() && $this->due_at->lt(today());
    }

    /** REC2026/0001 */
    public static function nextNumber(): string
    {
        $prefix = "REC" . now()->format("Y") . "/";
        $next = NumberSequence::next($prefix, fn () => NumberSequence::highestSuffix(
            static::where("number", "like", $prefix . "%")->pluck("number"),
            $prefix,
        ));

        return NumberSequence::format($prefix, $next, 4);
    }
}
