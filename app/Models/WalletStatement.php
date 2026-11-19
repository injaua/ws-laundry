<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A wallet statement (M-Pesa / e-Mola / mKesh) imported to check the recorded mobile payments. */
class WalletStatement extends Model
{
    use BelongsToStore;

    protected $fillable = ["provider", "file_name", "period_from", "period_to", "lines_count", "matched_count", "user_id"];

    protected function casts(): array
    {
        return ["period_from" => "date", "period_to" => "date"];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WalletStatementLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
