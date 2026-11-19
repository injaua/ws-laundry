<?php

namespace App\Actions\CashSessions;

use App\Models\CashSession;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenCashSession
{
    /**
     * @throws ValidationException when the operator already has an open shift
     */
    public function handle(User $user, float $openingFloat): CashSession
    {
        return DB::transaction(function () use ($user, $openingFloat) {
            // Serialise concurrent "open" clicks for the same operator.
            User::whereKey($user->id)->lockForUpdate()->first();

            if (CashSession::currentFor($user)) {
                throw ValidationException::withMessages([
                    "opening_float" => __("app.cash_session_already_open"),
                ]);
            }

            return CashSession::create([
                "user_id" => $user->id,
                "status" => CashSession::STATUS_OPEN,
                "opened_at" => now(),
                "opening_float" => Money::round($openingFloat),
            ]);
        });
    }
}
