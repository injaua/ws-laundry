<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Intake photo: the state of the garments when they arrived (evidence for complaints). */
class OrderPhoto extends Model
{
    use BelongsToStore;

    protected $fillable = ["order_id", "path", "caption", "user_id"];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return Storage::disk("public")->url($this->path);
    }
}
