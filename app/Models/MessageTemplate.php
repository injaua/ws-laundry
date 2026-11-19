<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageTemplate extends Model
{
    use BelongsToStore;
    use Loggable;

    protected $fillable = ["name", "body", "wa_template_name", "wa_language", "wa_params", "is_active", "created_by"];

    protected function casts(): array
    {
        return ["is_active" => "boolean", "wa_params" => "array"];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }

    /** Placeholders a template body may use, filled from the customer/order context. */
    public const PLACEHOLDERS = ["customer", "order", "amount", "due_date", "delivery_date", "days"];

    /** Body with {placeholders} replaced; unknown placeholders are left as typed. */
    public function render(array $vars): string
    {
        return strtr($this->body, collect($vars)->mapWithKeys(fn ($v, $k) => ["{" . $k . "}" => (string) $v])->all());
    }

    /** Ordered values for the Meta template's {{1}}, {{2}}… parameters. */
    public function waParameterValues(array $vars): array
    {
        return array_map(fn ($name) => (string) ($vars[$name] ?? ""), $this->wa_params ?? []);
    }

    public function isApprovedOnWhatsapp(): bool
    {
        return filled($this->wa_template_name);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, "template_id");
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CustomerMessageLog::class);
    }
}
