<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'service_id', 'package_id', 'type', 'weight',
        'amount', 'unit_price', 'subtotal'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Display name of whatever is being sold (service, package, or a removed package).
     */
    public function getItemNameAttribute(): string
    {
        return $this->service?->name
            ?? $this->package?->name
            ?? __('app.package_removed');
    }
}
