<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceJobItem extends Model
{
    use HasFactory;

    protected $fillable = ["maintenance_job_id", "inventory_item_id", "quantity", "inventory_movement_id"];

    protected function casts(): array
    {
        return ["quantity" => "decimal:2"];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(MaintenanceJob::class, "maintenance_job_id");
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, "inventory_item_id");
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, "inventory_movement_id");
    }
}
