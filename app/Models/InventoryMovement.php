<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class InventoryMovement extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    public const REASONS = ["purchase", "consumption", "breakage", "adjustment", "return", "maintenance"];

    /** Movements written by automated flows are immutable from the ledger UI. */
    public const LOCKED_SOURCES = ["order", "purchase_order"];

    protected $fillable = [
        "inventory_item_id", "inventory_lot_id", "direction", "quantity", "unit_cost",
        "reason", "expense_id", "user_id", "source_type", "source_id", "moved_at", "notes",
    ];

    protected function casts(): array
    {
        return [
            "quantity" => "decimal:2",
            "unit_cost" => "decimal:0",
            "moved_at" => "date",
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, "inventory_item_id");
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, "inventory_lot_id");
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stockDelta(): float
    {
        return ($this->direction === "in" ? 1 : -1) * (float) $this->quantity;
    }

    public function expenseAmount(): int
    {
        return (int) round((float) $this->quantity * (float) $this->unit_cost);
    }

    public function wantsExpense(bool $register): bool
    {
        return $register && $this->direction === "in" && $this->reason === "purchase";
    }

    public function isLocked(): bool
    {
        return in_array($this->source_type, self::LOCKED_SOURCES, true) || $this->reason === "maintenance";
    }

    /**
     * Record an inbound movement. When $lot carries a lot_number or expires_at,
     * an InventoryLot is created and linked to the movement.
     */
    public static function record(InventoryItem $item, array $data, bool $register, string $category, ?array $lot = null): static
    {
        $movement = null;

        DB::transaction(function () use ($item, $data, $register, $category, $lot, &$movement) {
            $locked = InventoryItem::whereKey($item->id)->lockForUpdate()->first();

            $movement = new static($data);
            $movement->inventory_item_id = $locked->id;

            if ($movement->direction === "in" && $lot && (!empty($lot["lot_number"]) || !empty($lot["expires_at"]))) {
                $lotRecord = InventoryLot::create([
                    "inventory_item_id" => $locked->id,
                    "lot_number" => $lot["lot_number"] ?: "L".now()->format("ymdHis"),
                    "quantity" => $movement->quantity,
                    "remaining_qty" => $movement->quantity,
                    "unit_cost" => $movement->unit_cost,
                    "expires_at" => $lot["expires_at"] ?? null,
                    "received_at" => $movement->moved_at,
                    "notes" => $lot["notes"] ?? null,
                ]);
                $movement->inventory_lot_id = $lotRecord->id;
            }

            $movement->save();

            $locked->stock_qty = (float) $locked->stock_qty + $movement->stockDelta();
            if ($movement->direction === "in" && $movement->unit_cost !== null) {
                $locked->unit_cost = $movement->unit_cost;
            }
            $locked->save();

            $movement->syncExpense($register, $category);
        });

        return $movement;
    }

    /**
     * Register one outbound movement per lot consumed (FIFO by expiry).
     * Consumes at most the available stock; leftovers hit legacy unloted stock.
     *
     * @return array{movements: array<int, InventoryMovement>, consumed: float, shortfall: float}
     */
    public static function consume(InventoryItem $item, float $qty, array $data, bool $allowExpired = false): array
    {
        $result = ["movements" => [], "consumed" => 0.0, "shortfall" => 0.0];
        $requested = round(max(0, $qty), 2);

        DB::transaction(function () use ($item, $requested, $data, $allowExpired, &$result) {
            $locked = InventoryItem::whereKey($item->id)->lockForUpdate()->first();
            $consumed = min($requested, max(0.0, round((float) $locked->stock_qty, 2)));

            if ($consumed > 0) {
                $left = $consumed;

                foreach (InventoryLot::allocate($locked->id, $consumed, $allowExpired) as $alloc) {
                    $result["movements"][] = static::create($data + [
                        "inventory_item_id" => $locked->id,
                        "inventory_lot_id" => $alloc["lot"]->id,
                        "quantity" => $alloc["qty"],
                    ]);
                    $left = round($left - $alloc["qty"], 2);
                }

                if ($left > 0.001) {
                    $result["movements"][] = static::create($data + [
                        "inventory_item_id" => $locked->id,
                        "quantity" => $left,
                    ]);
                }

                $locked->stock_qty = (float) $locked->stock_qty - $consumed;
                $locked->save();
            }

            $result["consumed"] = $consumed;
            $result["shortfall"] = round($requested - $consumed, 2);
        });

        return $result;
    }

    public function resync(array $data, bool $register, string $category): void
    {
        DB::transaction(function () use ($data, $register, $category) {
            $locked = InventoryItem::whereKey($this->inventory_item_id)->lockForUpdate()->first();

            $previous = $this->stockDelta();
            $this->fill($data);

            $locked->stock_qty = (float) $locked->stock_qty - $previous + $this->stockDelta();
            if ($this->direction === "in" && $this->unit_cost !== null) {
                $locked->unit_cost = $this->unit_cost;
            }
            $locked->save();

            if ($this->inventory_lot_id && $previous > 0 && $this->stockDelta() > 0) {
                $lot = $this->lot;
                $delta = $this->stockDelta() - $previous;
                $lot->quantity = (float) $lot->quantity + $delta;
                $lot->remaining_qty = (float) $lot->remaining_qty + $delta;
                $lot->save();
            }

            $this->save();
            $this->syncExpense($register, $category);
        });
    }

    /** Deleting this movement would leave a lot with negative remaining stock. */
    public function wouldBreakLot(): bool
    {
        if (!$this->inventory_lot_id) {
            return false;
        }

        return (float) $this->lot->remaining_qty - (float) $this->quantity < -0.001;
    }

    public function remove(): void
    {
        DB::transaction(function () {
            $locked = InventoryItem::whereKey($this->inventory_item_id)->lockForUpdate()->first();
            $locked->stock_qty = (float) $locked->stock_qty - $this->stockDelta();
            $locked->save();

            if ($this->inventory_lot_id) {
                $lot = $this->lot;
                if ($this->direction === "out") {
                    $lot->restore((float) $this->quantity);
                } else {
                    $lot->quantity = (float) $lot->quantity - (float) $this->quantity;
                    $lot->remaining_qty = (float) $lot->remaining_qty - (float) $this->quantity;
                    $lot->save();

                    if ((float) $lot->quantity <= 0.001) {
                        $lot->delete();
                    }
                }
            }

            $this->clearExpense();
            $this->delete();
        });
    }

    public function syncExpense(bool $register, string $category): void
    {
        if (!$this->wantsExpense($register)) {
            $this->clearExpense();

            return;
        }

        $title = $this->item->name." — ".__("app.movement_reason_purchase");

        $expense = $this->expense;
        if ($expense) {
            $expense->update(["title" => $title, "category" => $category, "amount" => $this->expenseAmount(), "expense_date" => $this->moved_at]);
        } else {
            $expense = Expense::create([
                "title" => $title,
                "category" => $category,
                "amount" => $this->expenseAmount(),
                "expense_date" => $this->moved_at,
                "user_id" => $this->user_id,
                "source_type" => "inventory_movement",
                "source_id" => $this->id,
            ]);
            $this->expense_id = $expense->id;
            $this->save();
        }

        $expense->postToJournal();
    }

    private function clearExpense(): void
    {
        if (!$this->expense_id) {
            return;
        }

        $expense = $this->expense;
        if ($expense) {
            $expense->delete();
            JournalEntry::forget("expense", $expense->id);
        }

        $this->expense_id = null;
        $this->save();
    }
}
