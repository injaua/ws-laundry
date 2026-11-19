<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrder extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    public const STATUSES = ["draft", "ordered", "partial", "received", "cancelled"];

    protected $fillable = [
        "po_number", "supplier_id", "user_id", "order_date", "expected_date", "status", "total", "notes",
    ];

    protected function casts(): array
    {
        return [
            "order_date" => "date",
            "expected_date" => "date",
            "total" => "decimal:0",
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn("status", ["draft", "ordered", "partial"]);
    }

    public function getStatusLabelAttribute(): string
    {
        return __("app.po_status_".$this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            "draft" => "secondary",
            "ordered" => "info",
            "partial" => "warning",
            "received" => "success",
            default => "danger",
        };
    }

    public function isEditable(): bool
    {
        return $this->status === "draft";
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ["ordered", "partial"], true);
    }

    /** Reserve the next "OC-%06d" number the same way journal entries reserve "LNC-%06d". */
    public static function draft(?array $attributes = null): static
    {
        $order = new static($attributes ?? []);
        $order->po_number = "TMP-".Str::upper(Str::random(12));
        $order->save();
        $order->update(["po_number" => sprintf("OC-%06d", $order->id)]);

        return $order;
    }

    public function recalcTotal(): void
    {
        $this->update(["total" => (int) $this->items()->sum("subtotal")]);
    }

    public function markOrdered(): void
    {
        if ($this->status !== "draft" || !$this->items()->exists()) {
            throw ValidationException::withMessages([
                "status" => __("app.po_cannot_order"),
            ]);
        }

        $this->update(["status" => "ordered"]);
    }

    public function cancel(): void
    {
        if ($this->items()->where("received_qty", ">", 0)->exists()) {
            throw ValidationException::withMessages([
                "status" => __("app.po_cannot_cancel_received"),
            ]);
        }

        if (!in_array($this->status, ["draft", "ordered"], true)) {
            throw ValidationException::withMessages([
                "status" => __("app.po_cannot_cancel"),
            ]);
        }

        $this->update(["status" => "cancelled"]);
    }

    /**
     * Receive goods on one line: creates the inbound movement (lot + expense +
     * journal posting through the standard ledger path) and advances the PO status.
     */
    public function receiveLine(User $user, PurchaseOrderItem $line, float $quantity, ?int $unitCost, ?array $lot, bool $registerExpense = true): InventoryMovement
    {
        if (!$this->canReceive() || $line->purchase_order_id !== $this->id) {
            throw ValidationException::withMessages([
                "quantity" => __("app.po_cannot_receive"),
            ]);
        }

        $pending = (float) $line->quantity - (float) $line->received_qty;

        if ($quantity <= 0 || $quantity > $pending + 0.001) {
            throw ValidationException::withMessages([
                "quantity" => __("app.po_receive_exceeds_line", ["qty" => qty($pending)]),
            ]);
        }

        $item = $line->item;
        $cost = $unitCost ?? (int) $line->unit_cost;

        return DB::transaction(function () use ($user, $line, $item, $quantity, $cost, $lot, $registerExpense) {
            $movement = InventoryMovement::record(
                $item,
                [
                    "direction" => "in",
                    "quantity" => $quantity,
                    "unit_cost" => $cost,
                    "reason" => "purchase",
                    "user_id" => $user->id,
                    "source_type" => "purchase_order",
                    "source_id" => $this->id,
                    "moved_at" => today()->toDateString(),
                    "notes" => __("app.po_receipt_note", ["number" => $this->po_number]),
                ],
                $registerExpense,
                $item->kind === "product" ? "cleaning_products" : "other",
                $lot,
            );

            $line->update(["received_qty" => (float) $line->received_qty + $quantity]);

            $allReceived = $this->items()->whereRaw("received_qty < quantity - 0.001")->doesntExist();
            $this->update(["status" => $allReceived ? "received" : "partial"]);

            return $movement;
        });
    }
}
