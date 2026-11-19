<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A stage change of a piece: who scanned it, where, and when. */
class OrderPieceEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ["order_piece_id", "user_id", "from_status", "to_status", "note"];

    protected function casts(): array
    {
        return ["created_at" => "datetime"];
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(OrderPiece::class, "order_piece_id");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
