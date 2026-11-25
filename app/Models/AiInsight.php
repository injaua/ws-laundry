<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something the system noticed by itself: an alert of a store (late orders, till shortages,
 * stock, receivables…) or a briefing (yesterday's figures and the forecast). Store insights go
 * to the store's owner; company insights (store_id null) to the admin.
 *
 * @property Carbon $period_date
 * @property array<string, mixed>|null $data
 */
class AiInsight extends Model
{
    public const AUDIENCES = ["store", "company"];

    public const KINDS = ["briefing", "alert"];

    public const SEVERITIES = ["critical", "warning", "info"];

    public const STATUSES = ["new", "seen", "resolved", "dismissed"];

    protected $fillable = [
        "store_id", "audience", "kind", "detector", "severity", "title", "body", "recommendation",
        "data", "link", "fingerprint", "period_date", "status", "ai_written",
    ];

    protected function casts(): array
    {
        return [
            "data" => "array",
            "period_date" => "date",
            "ai_written" => "boolean",
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<AiInsightFeedback, $this> */
    public function feedback(): HasMany
    {
        return $this->hasMany(AiInsightFeedback::class, "insight_id");
    }

    /**
     * What a user receives: the owner, their store's insights; the admin, the company's and
     * those of the store worked in. Nobody else receives insights.
     */
    public function scopeVisibleTo(Builder $query, User $user, ?int $storeId): Builder
    {
        if ($user->isAdmin()) {
            return $query->where(fn ($q) => $q->where("audience", "company")
                ->when($storeId, fn ($q) => $q->orWhere(fn ($s) => $s->where("audience", "store")->where("store_id", $storeId))));
        }
        if ($user->isOwner() && $storeId) {
            return $query->where("audience", "store")->where("store_id", $storeId);
        }

        return $query->whereRaw("1 = 0");
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn("status", ["new", "seen"]);
    }

    public function severityColor(): string
    {
        return match ($this->severity) {
            "critical" => "danger",
            "warning" => "warning",
            default => "info",
        };
    }

    public function severityIcon(): string
    {
        return match ($this->severity) {
            "critical" => "bi-exclamation-octagon-fill",
            "warning" => "bi-exclamation-triangle-fill",
            default => "bi-info-circle-fill",
        };
    }

    public function bodyHtml(): string
    {
        return AiMessage::markdown($this->body);
    }
}
