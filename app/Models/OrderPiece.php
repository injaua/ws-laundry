<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One garment of an order, tagged with its own code and tracked stage by stage. */
class OrderPiece extends Model
{
    use BelongsToStore;

    protected $fillable = [
        "order_id", "order_item_id", "seq", "code", "description", "color", "brand",
        "intake_notes", "issue", "status", "status_at",
    ];

    protected function casts(): array
    {
        return ["status_at" => "datetime", "seq" => "integer"];
    }

    public function order(): BelongsTo
    {
        // Still resolvable when the order is in the trash, so a scan can say so.
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, "order_item_id");
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderPieceEvent::class)->orderBy("id");
    }

    public function statusRank(?string $status = null): int
    {
        $rank = array_search($status ?? $this->status, Order::STATUSES, true);

        return $rank === false ? 0 : $rank;
    }

    /** Ready for the customer, or already handed over. */
    public function isReady(): bool
    {
        return $this->statusRank() >= $this->statusRank("ready");
    }

    /** Scanner input: codes are printed upper-case, but typed ones may not be. */
    public static function findByCode(string $code): ?self
    {
        return static::where("code", mb_strtoupper(trim($code)))->first();
    }
}
