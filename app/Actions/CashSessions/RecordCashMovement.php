<?php

namespace App\Actions\CashSessions;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Withdrawal (cash to the safe) or deposit (cash into the till) during an open shift. */
class RecordCashMovement
{
    public function handle(CashSession $session, User $user, string $type, float $amount, string $reason): CashMovement
    {
        return DB::transaction(function () use ($session, $user, $type, $amount, $reason) {
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (!$session->isOpen()) {
                throw ValidationException::withMessages(["amount" => __("app.cash_session_not_open")]);
            }

            $amount = Money::round($amount);

            if ($type === CashMovement::TYPE_WITHDRAWAL && $amount > $session->expectedCash()) {
                throw ValidationException::withMessages([
                    "amount" => __("app.cash_withdrawal_exceeds", [
                        "amount" => money($session->expectedCash()),
                    ]),
                ]);
            }

            return $session->movements()->create([
                "user_id" => $user->id,
                "type" => $type,
                "amount" => $amount,
                "reason" => $reason,
            ]);
        });
    }
}
