<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    protected $table = "equipments";

    public const TYPES = ["washer", "dryer", "iron", "ironing_table", "vehicle", "other"];

    public const STATUSES = ["operational", "faulty", "in_maintenance", "retired"];

    protected $fillable = [
        "name", "code", "type", "brand", "model", "serial_number",
        "purchase_date", "purchase_price", "supplier", "warranty_until",
        "location", "status", "photo", "notes",
        "capacity_kg", "cycles_count", "service_interval_cycles", "cycles_at_last_service",
    ];

    protected function casts(): array
    {
        return [
            "purchase_date" => "date",
            "warranty_until" => "date",
            "purchase_price" => "decimal:2",
            "capacity_kg" => "decimal:2",
        ];
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(EquipmentRecipe::class);
    }

    public function loads(): HasMany
    {
        return $this->hasMany(MachineLoad::class);
    }

    public function cyclesSinceService(): int
    {
        return max(0, (int) $this->cycles_count - (int) $this->cycles_at_last_service);
    }

    /** Preventive maintenance is due by cycles. */
    public function serviceDue(): bool
    {
        return $this->service_interval_cycles > 0 && $this->cyclesSinceService() >= (int) $this->service_interval_cycles;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            "operational" => "success",
            "faulty" => "danger",
            "in_maintenance" => "warning",
            default => "secondary",
        };
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(MaintenanceJob::class);
    }
}
