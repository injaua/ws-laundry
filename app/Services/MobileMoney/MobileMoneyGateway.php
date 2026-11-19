<?php

namespace App\Services\MobileMoney;

use App\Models\MobileTransaction;

interface MobileMoneyGateway
{
    /** C2B: asks the customer to confirm the payment with the PIN on the phone. */
    public function collect(MobileTransaction $tx): GatewayResult;

    /** B2C: sends money to the customer's wallet (refund). */
    public function refund(MobileTransaction $tx): GatewayResult;

    /** Asks the operator what happened to a request that had no final answer. */
    public function status(MobileTransaction $tx): GatewayResult;
}
