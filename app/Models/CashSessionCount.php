<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashSessionCount extends Model
{
    protected $fillable = ["cash_session_id", "method", "expected", "counted", "difference"];

    protected function casts(): array
    {
        return [
            "expected" => "decimal:2",
            "counted" => "decimal:2",
            "difference" => "decimal:2",
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, "cash_session_id");
    }
}
