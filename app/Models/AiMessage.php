<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Table\TableExtension;

/** A question (role "user") or an answer (role "assistant") of an AI conversation. */
class AiMessage extends Model
{
    protected $fillable = ["conversation_id", "role", "content", "tools", "failed"];

    protected function casts(): array
    {
        return [
            "tools" => "array",
            "failed" => "boolean",
        ];
    }

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, "conversation_id");
    }

    /** The answer as safe HTML: Markdown with raw HTML stripped and unsafe links refused. */
    public function html(): string
    {
        if ($this->role === "user") {
            return nl2br(e($this->content));
        }

        return self::markdown($this->content);
    }

    public static function markdown(string $text): string
    {
        return Str::markdown($text, ["html_input" => "strip", "allow_unsafe_links" => false], [new TableExtension]);
    }
}
