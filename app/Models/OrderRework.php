<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An order sent back to washing: failed quality check, manual decision or a complaint. */
class OrderRework extends Model
{
    public const UPDATED_AT = null;

    public const SOURCES = ["qc", "manual", "complaint"];

    protected $fillable = ["order_id", "from_status", "reason", "source", "complaint_id", "user_id", "created_at"];

    protected function casts(): array
    {
        return ["created_at" => "datetime"];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
