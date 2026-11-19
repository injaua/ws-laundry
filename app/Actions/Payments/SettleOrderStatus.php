<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderConsumption;

/**
 * After a payment: a ready order that is fully paid moves to delivering. Orders paid
 * in advance stay in production (they still have to be washed).
 * Returns a material-consumption warning when one applies.
 */
class SettleOrderStatus
{
    public function handle(Order $order, ?User $user = null): ?string
    {
        if ($order->remainingBalance() <= 0 && $order->status === "ready") {
            $order->update(["status" => "delivering"]);

            if ($user) {
                $consumption = app(OrderConsumption::class);

                return $consumption->shortfallMessage($consumption->consumeIfNeeded($order, $user));
            }
        }

        return null;
    }
}
