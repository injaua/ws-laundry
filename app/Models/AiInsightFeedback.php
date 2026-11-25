<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's "useful / not useful" on an insight; not useful ones are given to the next analyses. */
class AiInsightFeedback extends Model
{
    protected $table = "ai_insight_feedback";

    protected $fillable = ["insight_id", "user_id", "useful"];

    protected function casts(): array
    {
        return ["useful" => "boolean"];
    }

    /** @return BelongsTo<AiInsight, $this> */
    public function insight(): BelongsTo
    {
        return $this->belongsTo(AiInsight::class, "insight_id");
    }
}
