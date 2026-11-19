<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use Loggable;

    protected $fillable = ["code", "name", "symbol", "position", "decimals", "is_active"];

    protected $casts = ["is_active" => "boolean", "decimals" => "integer"];

    protected static function booted(): void
    {
        static::saved(fn () => SystemSettings::flush());
        static::deleted(fn () => SystemSettings::flush());
    }

    public function isDefault(): bool
    {
        return SystemSettings::get("currency_code") === $this->code;
    }

    public function example(): string
    {
        [$dec, $thousands] = SystemSettings::separators();
        $n = number_format(1234.5, $this->decimals, $dec, $thousands);

        return $this->position === "before" ? "{$this->symbol} {$n}" : "{$n} {$this->symbol}";
    }
}
