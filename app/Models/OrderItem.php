<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'service_id', 'package_id', 'type', 'weight',
        'amount', 'unit_price', 'list_price', 'promotion_id', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'list_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

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

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    /** Billed quantity: kg for weight lines, units for piece and package lines. */
    public function billedQuantity(): float
    {
        return $this->type === "by_weight" ? (float) $this->weight : (float) $this->amount;
    }

    /** Money taken off the catalog price on this line (promotion, negotiated price or discount). */
    public function discountAmount(): float
    {
        if ($this->list_price === null) {
            return 0.0;
        }

        return max(0.0, round(((float) $this->list_price - (float) $this->unit_price) * $this->billedQuantity()));
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
