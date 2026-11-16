<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class InventoryMovement extends Model
{
    use HasFactory, Loggable;

    public const REASONS = ["purchase", "consumption", "breakage", "adjustment", "return", "maintenance"];

    protected $fillable = [
        "inventory_item_id", "direction", "quantity", "unit_cost",
        "reason", "expense_id", "user_id", "moved_at", "notes",
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

    public static function record(InventoryItem $item, array $data, bool $register, string $category): static
    {
        $movement = null;

        DB::transaction(function () use ($item, $data, $register, $category, &$movement) {
            $locked = InventoryItem::whereKey($item->id)->lockForUpdate()->first();

            $movement = new static($data);
            $movement->inventory_item_id = $locked->id;
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

            $this->save();
            $this->syncExpense($register, $category);
        });
    }

    public function remove(): void
    {
        DB::transaction(function () {
            $locked = InventoryItem::whereKey($this->inventory_item_id)->lockForUpdate()->first();
            $locked->stock_qty = (float) $locked->stock_qty - $this->stockDelta();
            $locked->save();

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
