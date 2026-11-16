<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Loggable;

class Order extends Model
{
    use HasFactory, SoftDeletes, Loggable;

    public const STATUSES = [
        'received', 'washing', 'drying', 'ironing', 'folding', 'ready', 'delivering', 'completed',
    ];

    protected $fillable = [
        'order_number', 'customer_id', 'received_at', 'delivery_date', 'completed_at',
        'status', 'total_price', 'subtotal', 'iva_rate', 'iva_amount', 'notes'
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'delivery_date' => 'date',
            'completed_at' => 'date',
            'subtotal' => 'decimal:2',
            'iva_rate' => 'decimal:2',
            'iva_amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getStatusLabelAttribute()
    {
        return __('app.status_' . $this->status);
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'received' => 'secondary',
            'washing' => 'info',
            'drying' => 'primary',
            'ironing' => 'warning',
            'folding' => 'info',
            'ready' => 'success',
            'delivering' => 'primary',
            'completed' => 'success',
        ];
        return $colors[$this->status] ?? 'secondary';
    }

    public function statusRank(?string $status = null): int
    {
        $rank = array_search($status ?? $this->status, self::STATUSES, true);

        return $rank === false ? 0 : $rank;
    }

    public function allowedStatuses(): array
    {
        return array_slice(self::STATUSES, $this->statusRank());
    }

    public function canTransitionTo(string $status): bool
    {
        return array_search($status, self::STATUSES, true) !== false
            && $this->statusRank($status) >= $this->statusRank();
    }

    /**
     * Split a VAT-inclusive amount into [net, vat] using this order's snapshot rate.
     */
    public function splitGrossAmount(float $amount): array
    {
        $rate = (float) $this->iva_rate;
        if ($rate <= 0) {
            return [round($amount, 2), 0.0];
        }

        $vat = round($amount * $rate / (100 + $rate));

        return [round($amount - $vat, 2), $vat];
    }
}
