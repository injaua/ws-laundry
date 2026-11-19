<?php

namespace App\Actions\CashSessions;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\CashSession;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes a shift from a blind count: the operator declares what they hold per
 * payment method, the expected amounts are frozen next to it, and a cash
 * shortage or surplus is booked to the journal.
 */
class CloseCashSession
{
    /**
     * @param  array<string, float|int|string|null>  $counted  per payment method
     * @return array{session: CashSession, warning: ?string}
     *
     * @throws ValidationException when there is a difference and no justification
     */
    public function handle(CashSession $session, User $closer, array $counted, ?string $note): array
    {
        return DB::transaction(function () use ($session, $closer, $counted, $note) {
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (!$session->isOpen()) {
                throw ValidationException::withMessages(["counted" => __("app.cash_session_not_open")]);
            }

            $expected = $session->expectedByMethod();
            $rows = [];
            $hasDifference = false;

            foreach (Payment::METHODS as $method) {
                $count = Money::round($counted[$method] ?? 0);
                $difference = Money::round($count - $expected[$method]);
                $hasDifference = $hasDifference || $difference != 0.0;

                $rows[] = [
                    "method" => $method,
                    "expected" => $expected[$method],
                    "counted" => $count,
                    "difference" => $difference,
                ];
            }

            $note = trim((string) $note);
            if ($hasDifference && $note === "") {
                // Deliberately generic: a blind count must not reveal the expected amounts.
                throw ValidationException::withMessages([
                    "closing_note" => __("app.cash_difference_needs_note"),
                ]);
            }

            $session->counts()->createMany($rows);
            $session->update([
                "status" => CashSession::STATUS_CLOSED,
                "closed_at" => now(),
                "closed_by" => $closer->id,
                "closing_note" => $note !== "" ? $note : null,
            ]);

            $cashDifference = collect($rows)->firstWhere("method", "cash")["difference"];
            $warning = $cashDifference != 0.0 ? $this->postCashDifference($session, $cashDifference) : null;

            return ["session" => $session->fresh(["counts"]), "warning" => $warning];
        });
    }

    /**
     * Shortage: D 6.8.9.9 Outros gastos e perdas / C 1.1 Caixa.
     * Surplus:  D 1.1 Caixa / C 7.6.9.9 Outros rendimentos e ganhos.
     * Non-cash differences are reconciliation errors, reviewed rather than booked.
     */
    private function postCashDifference(CashSession $session, float $difference): ?string
    {
        if ($period = AccountingPeriod::closedOn($session->closed_at)) {
            return __("app.period_closed", ["period" => $period->label()]);
        }

        $cash = Account::findByCode("1.1")?->id;
        [$debit, $credit] = $difference < 0
            ? [Account::findByCode("6.8.9.9")?->id, $cash]
            : [$cash, Account::findByCode("7.6.9.9")?->id];

        $entry = JournalEntry::postPair(
            $debit,
            $credit,
            abs($difference),
            $session->closed_at->toDateString(),
            __($difference < 0 ? "app.cash_shortage" : "app.cash_surplus") . " " . $session->label(),
            "cash_session",
            $session->id,
        );

        return $entry ? null : __("app.journal_skipped");
    }
}
