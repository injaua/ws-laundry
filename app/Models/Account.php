<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    public const TYPES = ["asset", "liability", "equity", "revenue", "expense"];

    protected $fillable = ["code", "name", "type", "is_group", "is_active", "parent_id"];

    protected function casts(): array
    {
        return [
            "is_group" => "boolean",
            "is_active" => "boolean",
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, "parent_id");
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, "parent_id");
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public static function findByCode(string $code): ?self
    {
        return static::where("code", $code)->where("is_group", false)->first();
    }

    public function isManualUsable(): bool
    {
        return !$this->is_group && $this->is_active;
    }
}
