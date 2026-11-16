<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Loggable;

class Customer extends Model
{
    use HasFactory, Loggable;

    protected $fillable = ['name', 'phone', 'address'];

    protected $appends = ['points', 'total_transactions'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function loyaltyPoints()
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    /**
     * Earn loyalty points when order is completed.
     * 1 point per 1,000 Mts (or 10 points per 10,000 Mts).
     */
    public function earnPoints(Order $order): void
    {
        $pointsEarned = floor($order->total_price / 1000);

        if ($pointsEarned > 0) {
            LoyaltyPoint::create([
                "customer_id" => $this->id,
                "points" => $pointsEarned,
                "type" => "earned",
                "note" => "Order #" . $order->order_number,
                "source_type" => Order::class,
                "source_id" => $order->id,
            ]);

            $this->increment("points", $pointsEarned);
        }
    }

    /**
     * Redeem loyalty points for discount.
     * 100 points = 5,000 Mts discount.
     */
    public function redeemPoints(int $points, Order $order): int
    {
        if ($this->points < $points) {
            throw new \Exception(__('app.insufficient_points', ['available' => $this->points]));
        }

        $discount = floor($points / 100) * 5000;

        if ($discount > $order->total_price) {
            $discount = $order->total_price;
            $points = ceil($discount / 5000) * 100;
        }

        LoyaltyPoint::create([
            "customer_id" => $this->id,
            "points" => -$points,
            "type" => "redeemed",
            "note" => "Points redeemed for order #" . $order->order_number . " (discount " . number_format($discount, 0, ".", ",") . " Mts)",
            "source_type" => Order::class,
            "source_id" => $order->id,
        ]);

        $this->decrement("points", $points);
        $order->decrement("total_price", $discount);

        return $discount;
    }

    public function getPointsAttribute(): int
    {
        return $this->attributes["points"] ?? 0;
    }

    public function getTotalTransactionsAttribute(): int
    {
        return $this->orders()->count();
    }
}
