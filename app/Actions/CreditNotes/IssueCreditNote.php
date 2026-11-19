<?php

namespace App\Actions\CreditNotes;

use App\Models\AccountingPeriod;
use App\Models\CashSession;
use App\Models\Complaint;
use App\Models\CreditNote;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues a credit note against an order. The amount can never exceed what is left of
 * the invoice; a refund can never exceed what the customer actually paid (and was not
 * yet given back); a deduction can never exceed what is still owed. Cash refunds come
 * out of the operator's open till, so the till count stays right.
 */
class IssueCreditNote
{
    /**
     * @param  array{amount: numeric, reason: string, description: string, settlement: string, refund_method?: ?string, issued_at?: ?string, complaint_id?: ?int}  $data
     * @return array{note: CreditNote, warning: ?string}
     *
     * @throws ValidationException
     */
    public function handle(Order $order, array $data, User $user): array
    {
        $amount = round((float) $data["amount"], 2);
        $issuedAt = $data["issued_at"] ?? now()->toDateString();
        $refund = $data["settlement"] === "refund";

        AccountingPeriod::assertOpen($issuedAt, "issued_at");

        if ($order->trashed()) {
            $this->fail("amount", __("app.cn_order_deleted"));
        }

        $creditable = $order->netTotal();
        if ($amount > $creditable + 0.004) {
            $this->fail("amount", __("app.cn_exceeds_invoice", ["amount" => $this->money($creditable)]));
        }

        if ($refund) {
            $refundable = $order->netPaid();
            if ($amount > $refundable + 0.004) {
                $this->fail("amount", __("app.cn_exceeds_paid", ["amount" => $this->money($refundable)]));
            }
        } else {
            $owed = $order->remainingBalance();
            if ($amount > $owed + 0.004) {
                $this->fail("settlement", __("app.cn_exceeds_owed", ["amount" => $this->money($owed)]));
            }
        }

        $method = $refund ? ($data["refund_method"] ?? null) : null;
        $session = CashSession::currentFor($user);
        if ($method === "cash" && !$session) {
            $this->fail("refund_method", __("app.cn_cash_needs_till"));
        }

        $complaint = !empty($data["complaint_id"]) ? Complaint::find($data["complaint_id"]) : null;
        [$net, $vat] = $order->splitGrossAmount($amount);

        for ($attempt = 1; ; $attempt++) {
            try {
                $note = DB::transaction(function () use ($order, $data, $user, $amount, $issuedAt, $refund, $method, $session, $complaint, $net, $vat) {
                    $note = CreditNote::create([
                        "number" => CreditNote::nextNumber(Carbon::parse($issuedAt)),
                        "order_id" => $order->id,
                        "customer_id" => $order->customer_id,
                        "complaint_id" => $complaint?->id,
                        "issued_at" => $issuedAt,
                        "reason" => $data["reason"],
                        "description" => $data["description"],
                        "amount" => $amount,
                        "net_amount" => $net,
                        "vat_amount" => $vat,
                        "vat_rate" => (float) $order->vat_rate,
                        "settlement" => $refund ? "refund" : "deduct",
                        "refund_method" => $method,
                        // Cash sales book revenue on payment, credit sales on delivery.
                        "reverses_revenue" => !$order->is_credit || $order->status === "completed",
                        // A refund leaves the till the operator is working on.
                        "cash_session_id" => $refund ? $session?->id : null,
                        "user_id" => $user->id,
                    ]);

                    if ($complaint && !$complaint->isClosed()) {
                        $complaint->update([
                            "status" => "resolved",
                            "resolution" => "credit_note",
                            "resolution_notes" => trim(($complaint->resolution_notes ? $complaint->resolution_notes . "\n" : "") . $note->number),
                            "resolved_by" => $user->id,
                            "resolved_at" => now(),
                        ]);
                    }

                    return $note;
                });
                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }

        $warning = $note->needsJournal() && $note->postToJournal() === null ? __("app.journal_skipped") : null;

        return ["note" => $note, "warning" => $warning];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function money(float $amount): string
    {
        return money($amount);
    }
}
