<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory, Loggable;

    public const STATUSES = ["probation", "active", "suspended", "terminated"];

    protected $fillable = [
        "name", "bi_number", "position", "phone", "address", "birth_date",
        "hire_date", "probation_ends_at", "status", "contract_type", "base_salary",
        "termination_date", "termination_reason", "user_id", "created_by", "notes",
    ];

    protected function casts(): array
    {
        return [
            "birth_date" => "date",
            "hire_date" => "date",
            "probation_ends_at" => "date",
            "termination_date" => "date",
            "base_salary" => "decimal:0",
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function justifications(): HasMany
    {
        return $this->hasMany(AbsenceJustification::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ["probation", "active"]);
    }

    public function isSystemUser(): bool
    {
        return $this->user_id !== null;
    }

    public function getStatusLabelAttribute(): string
    {
        return __("app.status_".$this->status);
    }

    public function scopeWorking($query)
    {
        return $query->whereIn("status", ["probation", "active", "suspended"]);
    }
}
