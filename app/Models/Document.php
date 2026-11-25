<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file of the document archive (Word, Excel, PowerPoint or PDF). The file sits on the
 * private disk and is only served through DocumentController; it is removed with the row.
 *
 * A private document (sent through the exchange) is seen only by whoever uploaded it and the
 * colleagues it was sent to; the others see every document of the store.
 *
 * @property Carbon|null $document_date
 * @property Carbon|null $expires_at
 * @property array<int, string>|null $tags
 * @property bool $is_private
 */
class Document extends Model
{
    use BelongsToStore;
    use Loggable;

    public const DISK = "local";

    /** Days ahead that count as "expiring soon". */
    public const EXPIRING_DAYS = 30;

    /** Type => accepted extensions, Bootstrap icon and colour. */
    public const TYPES = [
        "word" => ["ext" => ["doc", "docx"], "icon" => "bi-file-earmark-word", "color" => "primary"],
        "excel" => ["ext" => ["xls", "xlsx"], "icon" => "bi-file-earmark-excel", "color" => "success"],
        "powerpoint" => ["ext" => ["ppt", "pptx"], "icon" => "bi-file-earmark-ppt", "color" => "warning"],
        "pdf" => ["ext" => ["pdf"], "icon" => "bi-file-earmark-pdf", "color" => "danger"],
    ];

    protected $fillable = [
        "folder_id", "category_id", "title", "reference", "description", "tags", "document_date",
        "expires_at", "path", "original_name", "mime_type", "extension", "size", "uploaded_by", "is_private",
    ];

    protected function casts(): array
    {
        return [
            "tags" => "array",
            "document_date" => "date",
            "expires_at" => "date",
            "size" => "integer",
            "is_private" => "boolean",
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            $document->uploaded_by ??= auth()->id();
        });

        static::deleted(function (Document $document) {
            Storage::disk(self::DISK)->delete($document->path);
        });
    }

    /** @return string[] every accepted extension */
    public static function extensions(): array
    {
        return array_merge(...array_column(self::TYPES, "ext"));
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, "folder_id");
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, "category_id");
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, "uploaded_by");
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, "document_favorites");
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    /** Documents $user may see: every shared one, plus the private ones they uploaded, sent or received. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $id = (int) $user->id;

        return $query->where(fn ($q) => $q->where("documents.is_private", false)
            ->orWhere("documents.uploaded_by", $id)
            ->orWhereHas("shares", fn ($s) => $s->where("sender_id", $id)->orWhere("recipient_id", $id)));
    }

    public function isVisibleTo(User $user): bool
    {
        if (!$this->is_private || (int) $this->uploaded_by === (int) $user->id) {
            return true;
        }

        return $this->shares()->where(fn ($s) => $s->where("sender_id", $user->id)->orWhere("recipient_id", $user->id))->exists();
    }

    /** Saves the file on the private disk; @return array<string, mixed> the file columns */
    public static function storeUpload(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        $storeId = CurrentStore::id() ?? 0;

        return [
            "path" => $file->storeAs("documents/{$storeId}", $file->hashName(), self::DISK),
            "original_name" => mb_substr($file->getClientOriginalName(), 0, 255),
            "mime_type" => $file->getMimeType(),
            "extension" => $extension,
            "size" => $file->getSize(),
        ];
    }

    public function scopeFavoriteOf(Builder $query, int $userId): Builder
    {
        return $query->whereHas("favoritedBy", fn ($q) => $q->whereKey($userId));
    }

    /** Expiring from today up to EXPIRING_DAYS ahead. */
    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query->whereBetween("expires_at", [today(), today()->addDays(self::EXPIRING_DAYS)]);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->whereIn("extension", self::TYPES[$type]["ext"] ?? []);
    }

    public function type(): string
    {
        foreach (self::TYPES as $type => $info) {
            if (in_array($this->extension, $info["ext"], true)) {
                return $type;
            }
        }

        return "pdf";
    }

    public function icon(): string
    {
        return self::TYPES[$this->type()]["icon"];
    }

    public function color(): string
    {
        return self::TYPES[$this->type()]["color"];
    }

    public function isPdf(): bool
    {
        return $this->extension === "pdf";
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(today());
    }

    public function isExpiringSoon(): bool
    {
        return $this->expires_at !== null && !$this->isExpired()
            && $this->expires_at->lte(today()->addDays(self::EXPIRING_DAYS));
    }

    public static function formatSize(int $bytes): string
    {
        $units = ["B", "KB", "MB", "GB", "TB"];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $value : number_format($value, 1, ",", "")) . " " . $units[$i];
    }
}
