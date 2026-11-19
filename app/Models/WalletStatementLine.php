<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One incoming movement of a wallet statement and its match with the recorded payments. */
class WalletStatementLine extends Model
{
    use BelongsToStore;

    public const STATUSES = ["matched", "amount_mismatch", "unmatched", "ignored"];

    protected $fillable = [
        "wallet_statement_id", "provider", "transaction_ref", "occurred_at", "amount", "phone", "description", "status", "recorded_amount", "match_note",
    ];

    protected function casts(): array
    {
        return ["occurred_at" => "datetime", "amount" => "decimal:2", "recorded_amount" => "decimal:2"];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(WalletStatement::class, "wallet_statement_id");
    }

    public function statusColor(): string
    {
        return ["matched" => "success", "amount_mismatch" => "danger", "unmatched" => "warning", "ignored" => "secondary"][$this->status] ?? "secondary";
    }
}
