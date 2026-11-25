<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A folder of the document archive. Deleting it leaves its documents without a folder. */
class DocumentFolder extends Model
{
    use BelongsToStore;

    public const COLORS = ["primary", "success", "danger", "warning", "info", "secondary", "dark"];

    protected $fillable = ["name", "color"];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, "folder_id");
    }
}
