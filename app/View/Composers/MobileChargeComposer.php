<?php

namespace App\View\Composers;

use App\Models\MobileTransaction;
use App\Models\Order;
use App\Services\MobileMoney\MobileMoneySettings;
use Illuminate\View\View;

/** Data of the "charge by mobile wallet" box (mobile-payments._charge). Needs $order. */
class MobileChargeComposer
{
    public function compose(View $view): void
    {
        /** @var Order $order */
        $order = $view->getData()["order"];
        $phone = $order->customer?->phone;

        $view->with([
            "mmProviders" => MobileMoneySettings::enabledProviders(),
            "mmRemaining" => round($order->remainingBalance(), 2),
            "mmPhone" => $phone,
            "mmGuess" => MobileTransaction::providerForPhone($phone),
            "mmRecent" => $order->mobileTransactions()->where("type", "c2b")->limit(3)->get(),
        ]);
    }
}
