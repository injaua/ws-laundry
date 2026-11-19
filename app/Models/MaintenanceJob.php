<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MaintenanceJob extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    public const KINDS = ["preventive", "corrective"];

    public const STATUSES = ["scheduled", "in_progress", "completed", "cancelled"];

    protected $fillable = [
        "equipment_id", "kind", "title", "description", "fault_description",
        "technician", "employee_id", "service_date", "cost", "expense_id",
        "status", "next_service_date", "completed_at",
    ];

    protected function casts(): array
    {
        return [
            "service_date" => "date",
            "next_service_date" => "date",
            "cost" => "decimal:0",
            "completed_at" => "datetime",
        ];
    }

    protected static function booted(): void
    {
        // A finished preventive service restarts the machine's cycle counter.
        static::saved(function (MaintenanceJob $job) {
            if (($job->wasRecentlyCreated || $job->wasChanged("status")) && $job->status === "completed" && $job->kind === "preventive" && $job->equipment) {
                $job->equipment->update(["cycles_at_last_service" => $job->equipment->cycles_count]);
            }
        });
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MaintenanceJobItem::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            "scheduled" => "info",
            "in_progress" => "warning",
            "completed" => "success",
            default => "secondary",
        };
    }

    public function addPart(InventoryItem $item, float $quantity): void
    {
        if ($this->parts()->where("inventory_item_id", $item->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($item, $quantity) {
            $locked = InventoryItem::whereKey($item->id)->lockForUpdate()->first();
            $locked->stock_qty = (float) $locked->stock_qty - $quantity;
            $locked->save();

            $movement = InventoryMovement::create([
                "inventory_item_id" => $locked->id,
                "direction" => "out",
                "quantity" => $quantity,
                "reason" => "maintenance",
                "user_id" => auth()->id(),
                "moved_at" => $this->service_date,
                "notes" => $this->title,
            ]);

            $this->parts()->create([
                "inventory_item_id" => $locked->id,
                "quantity" => $quantity,
                "inventory_movement_id" => $movement->id,
            ]);
        });
    }

    public function removePart(MaintenanceJobItem $part): void
    {
        DB::transaction(function () use ($part) {
            if ($part->inventory_movement_id) {
                $movement = $part->movement;
                $locked = InventoryItem::whereKey($part->inventory_item_id)->lockForUpdate()->first();
                $locked->stock_qty = (float) $locked->stock_qty + (float) $movement->quantity;
                $locked->save();
                $movement->delete();
            }

            $part->delete();
        });
    }

    public function cancel(): void
    {
        DB::transaction(function () {
            foreach ($this->parts()->get() as $part) {
                $this->removePart($part);
            }

            if ($this->expense_id) {
                $expense = $this->expense;
                if ($expense) {
                    $expense->delete();
                    JournalEntry::forget("expense", $expense->id);
                }
                $this->expense_id = null;
            }

            $this->status = "cancelled";
            $this->save();
        });
    }

    public function syncExpense(bool $register, string $category): void
    {
        $needed = $register && (float) $this->cost > 0;

        if (!$needed) {
            if ($this->expense_id) {
                $expense = $this->expense;
                if ($expense) {
                    $expense->delete();
                    JournalEntry::forget("expense", $expense->id);
                }
                $this->expense_id = null;
                $this->save();
            }

            return;
        }

        $title = $this->title." — ".__("app.maintenance");

        $expense = $this->expense;
        if ($expense) {
            $expense->update(["title" => $title, "category" => $category, "amount" => (int) round((float) $this->cost), "expense_date" => $this->service_date]);
        } else {
            $expense = Expense::create([
                "title" => $title,
                "category" => $category,
                "amount" => (int) round((float) $this->cost),
                "expense_date" => $this->service_date,
                "user_id" => auth()->id(),
                "source_type" => "maintenance_job",
                "source_id" => $this->id,
            ]);
            $this->expense_id = $expense->id;
            $this->save();
        }

        $expense->postToJournal();
    }
}
