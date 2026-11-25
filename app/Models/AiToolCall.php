<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit of one tool the AI asked to run: who, which store, what input, the outcome. */
class AiToolCall extends Model
{
    public const UPDATED_AT = null;

    public const STATUSES = ["ok", "denied", "error"];

    protected $fillable = ["conversation_id", "message_id", "user_id", "store_id", "tool", "input", "status", "duration_ms"];

    protected function casts(): array
    {
        return ["input" => "array"];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
