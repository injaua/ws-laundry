<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use HasFactory, Loggable;

    protected $table = "equipments";

    public const TYPES = ["washer", "dryer", "iron", "ironing_table", "vehicle", "other"];

    public const STATUSES = ["operational", "faulty", "in_maintenance", "retired"];

    protected $fillable = [
        "name", "code", "type", "brand", "model", "serial_number",
        "purchase_date", "purchase_price", "supplier", "warranty_until",
        "location", "status", "photo", "notes",
    ];

    protected function casts(): array
    {
        return [
            "purchase_date" => "date",
            "warranty_until" => "date",
            "purchase_price" => "decimal:0",
        ];
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
