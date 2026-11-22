<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    protected $fillable = ['name', 'type', 'price', 'estimated_days', 'photo'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(ServiceRecipe::class);
    }
}
