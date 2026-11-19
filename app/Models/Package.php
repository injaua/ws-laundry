<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use BelongsToStore;
    use HasFactory;

    protected $fillable = ["name", "description", "weight_kg", "price", "is_active"];

    protected function casts(): array
    {
        return [
            "weight_kg" => "decimal:2",
            "price" => "decimal:0",
            "is_active" => "boolean",
        ];
    }

    public function recipes()
    {
        return $this->hasMany(PackageRecipe::class);
    }
}
