<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    protected $fillable = ['name', 'type', 'price', 'estimated_days', 'photo'];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function recipes()
    {
        return $this->hasMany(ServiceRecipe::class);
    }
}
