<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cash from the safe and/or cheques taken to the bank on one deposit slip. */
class BankDeposit extends Model
{
    use BelongsToStore;
    use Loggable;

    protected $fillable = ["deposited_at", "slip_number", "cash_amount", "cheques_amount", "total", "note", "user_id", "confirmed_at"];

    protected function casts(): array
    {
        return [
            "deposited_at" => "date",
            "cash_amount" => "decimal:2",
            "cheques_amount" => "decimal:2",
            "total" => "decimal:2",
            "confirmed_at" => "datetime",
        ];
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return "DEP-" . str_pad((string) $this->id, 5, "0", STR_PAD_LEFT);
    }
}
