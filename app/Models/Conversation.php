<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use BelongsToStore;

    public const TYPES = ["direct", "meeting"];

    protected $fillable = ["type", "title", "created_by"];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, "conversation_user")->withPivot("last_read_at");
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function isDirect(): bool
    {
        return $this->type === "direct";
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas("participants", fn ($q) => $q->whereKey($user->id));
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants->contains($user->id);
    }

    public function displayName(User $viewer): string
    {
        if ($this->isDirect()) {
            return $this->participants->firstWhere("id", "!=", $viewer->id)?->name ?? __("app.internal_chat");
        }

        return $this->title ?? __("app.meeting");
    }

    public function unreadCountFor(User $user): int
    {
        $lastRead = $this->participants->firstWhere("id", $user->id)?->pivot->last_read_at;

        return $this->messages()
            ->where("user_id", "!=", $user->id)
            ->when($lastRead, fn ($q) => $q->where("created_at", ">", $lastRead))
            ->when(!$lastRead, fn ($q) => $q->where("created_at", ">=", $this->created_at))
            ->count();
    }
}
