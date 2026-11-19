<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cash taken out of the till to the safe (withdrawal) or added to it (deposit). */
class CashMovement extends Model
{
    use BelongsToStore;
    use Loggable;

    public const TYPE_WITHDRAWAL = "withdrawal";

    public const TYPE_DEPOSIT = "deposit";

    public const TYPES = [self::TYPE_WITHDRAWAL, self::TYPE_DEPOSIT];

    protected $fillable = ["cash_session_id", "user_id", "type", "amount", "reason"];

    protected function casts(): array
    {
        return ["amount" => "decimal:2"];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, "cash_session_id");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
