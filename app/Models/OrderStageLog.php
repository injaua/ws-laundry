<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One stage change of an order: the basis for time-per-stage, on-time and productivity figures. */
class OrderStageLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ["order_id", "from_status", "to_status", "user_id", "employee_id", "note", "created_at"];

    protected function casts(): array
    {
        return ["created_at" => "datetime"];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
