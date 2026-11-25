<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversation of one user with the AI assistant. Private to its user; it remembers the
 * store it was held in (the assistant's answers are about that store).
 */
class AiConversation extends Model
{
    protected $fillable = ["store_id", "user_id", "title"];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<AiMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, "conversation_id")->orderBy("id");
    }

    /**
     * @param  Builder<AiConversation>  $query
     * @return Builder<AiConversation>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where("user_id", $user->id);
    }
}
