<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use App\Support\Languages;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use Loggable;

    /** Translation files every language has (lang/{code}/{group}.php). */
    public const GROUPS = ["app", "validation", "auth"];

    /** Languages with files in lang/ (the base a new language is copied from). */
    public const BASES = ["pt", "en"];

    protected $fillable = ["code", "name", "flag", "direction", "base_locale", "is_active", "is_system"];

    protected $casts = ["is_active" => "boolean", "is_system" => "boolean"];

    protected static function booted(): void
    {
        static::saved(fn (Language $l) => Languages::flush($l->code));
        static::deleted(fn (Language $l) => Languages::flush($l->code));
    }

    public function isRtl(): bool
    {
        return $this->direction === "rtl";
    }

    public function label(): string
    {
        return trim(($this->flag ? $this->flag . " " : "") . $this->name);
    }
}
