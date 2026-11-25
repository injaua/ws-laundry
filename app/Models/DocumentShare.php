<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One document sent by one colleague to another (admin, owner, accountant of the store).
 * The recipient sees it in "Received" until they remove it; read_at is set when they open it.
 *
 * @property int $document_id
 * @property int|null $sender_id
 * @property int $recipient_id
 * @property string|null $message
 * @property Carbon|null $read_at
 */
class DocumentShare extends Model
{
    use BelongsToStore;
    use Loggable;

    /** Roles that exchange documents with each other. */
    public const ROLES = ["admin", "owner", "accountant"];

    protected $fillable = ["document_id", "sender_id", "recipient_id", "message", "read_at"];

    protected function casts(): array
    {
        return [
            "document_id" => "integer",
            "sender_id" => "integer",
            "recipient_id" => "integer",
            "read_at" => "datetime",
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, "sender_id");
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, "recipient_id");
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull("read_at");
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Who $user can send documents to: the active admins, owners and accountants of the store.
     *
     * @return Collection<int, User>
     */
    public static function recipientsFor(User $user): Collection
    {
        return User::colleagues()
            ->where("is_active", true)
            ->whereIn("role", self::ROLES)
            ->whereKeyNot($user->id)
            ->orderBy("name")
            ->get(["id", "name", "role", "store_id"]);
    }

    /** Marks as read what $userId received of this document. */
    public static function markRead(Document $document, int $userId): void
    {
        static::where("document_id", $document->id)->where("recipient_id", $userId)->unread()
            ->update(["read_at" => now()]);
    }
}
