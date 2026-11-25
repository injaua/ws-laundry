<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call to the AI provider (an answer, or an insights analysis): tokens and cost.
 * store_id null = company level. The monthly budget is checked against these rows.
 */
class AiRun extends Model
{
    public const UPDATED_AT = null;

    public const PURPOSES = ["chat", "insights", "company", "test"];

    protected $fillable = ["store_id", "user_id", "purpose", "provider", "model", "input_tokens", "output_tokens", "cost", "succeeded", "error"];

    protected function casts(): array
    {
        return [
            "cost" => "decimal:6",
            "succeeded" => "boolean",
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Runs paid from a settings level: a store's own configuration, or the company's (null). */
    public function scopeAtLevel(Builder $query, ?int $storeId): Builder
    {
        return $storeId === null ? $query->whereNull("store_id") : $query->where("store_id", $storeId);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->where("created_at", ">=", now()->startOfMonth());
    }
}
