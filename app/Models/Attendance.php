<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    public const STATUSES = ["present", "absent", "late", "excused", "vacation"];

    protected $fillable = [
        "employee_id", "attendance_date", "status", "notes", "registered_by",
    ];

    protected function casts(): array
    {
        return [
            "attendance_date" => "date:Y-m-d",
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, "registered_by");
    }
}
