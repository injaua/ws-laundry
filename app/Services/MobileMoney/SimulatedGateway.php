<?php

namespace App\Services\MobileMoney;

use App\Models\MobileTransaction;
use Illuminate\Support\Str;

/**
 * Simulation: no money moves. Lets the team practise the whole flow before the
 * operator credentials exist. Numbers ending in 0000 are refused (no balance) and
 * numbers ending in 1111 never answer (the request expires).
 */
class SimulatedGateway implements MobileMoneyGateway
{
    public function __construct(private string $provider) {}

    public function collect(MobileTransaction $tx): GatewayResult
    {
        return $this->answer($tx);
    }

    public function refund(MobileTransaction $tx): GatewayResult
    {
        return $this->answer($tx);
    }

    public function status(MobileTransaction $tx): GatewayResult
    {
        return $this->answer($tx);
    }

    private function answer(MobileTransaction $tx): GatewayResult
    {
        if (str_ends_with($tx->phone, "0000")) {
            return GatewayResult::failed("SIM-INSUFFICIENT", __("app.mm_sim_insufficient"), ["simulated" => true]);
        }

        if (str_ends_with($tx->phone, "1111")) {
            return GatewayResult::pending("SIM-TIMEOUT", __("app.mm_sim_no_answer"), ["simulated" => true]);
        }

        $ref = strtoupper(($this->provider === "mpesa" ? "SIM" : "SIE") . Str::random(7));

        return GatewayResult::success($ref, "SIM-" . $tx->reference, "SIM-0", __("app.mm_sim_ok"), ["simulated" => true]);
    }
}
