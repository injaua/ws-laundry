<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrialBalanceArchiveLine extends Model
{
    public $timestamps = false;

    protected $fillable = ["trial_balance_archive_id", "account_code", "account_name", "account_type", "total_debit", "total_credit", "balance_debit", "balance_credit"];

    public function archive(): BelongsTo
    {
        return $this->belongsTo(TrialBalanceArchive::class, "trial_balance_archive_id");
    }
}
