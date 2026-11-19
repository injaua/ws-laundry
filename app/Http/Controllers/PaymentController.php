<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SettleOrderStatus;
use App\Http\Requests\PaymentRequest;
use App\Models\AccountingPeriod;
use App\Models\CashSession;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Payments\Cheques;
use App\Services\WhatsApp\AutomaticNotifications;
use App\Support\Documents\DocumentResponse;
use App\Support\Money;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request)
    {
        $validated = $request->paymentData();
        $order = $request->order();

        // A completed order is closed for payments, except credit orders: those are
        // exactly the ones that settle after delivery.
        if ($order->status === "completed" && !$order->is_credit) {
            return redirect()->route("orders.show", $order)
                ->with("error", __('app.payment_locked_error'));
        }

        $remaining = $order->remainingBalance(); // after credit notes and refunds
        if (!Money::isPositive($remaining)) {
            return redirect()->route("orders.show", $order)
                ->with("error", __('app.order_fully_paid'));
        }
        if (Money::compare($validated["amount"], $remaining) > 0) {
            return back()->withInput()
                ->withErrors(["amount" => __('app.payment_exceeds_remaining', [
                    "amount" => money($remaining),
                ])]);
        }

        // Every payment belongs to the operator's open till shift. Cashiers must have
        // one; admin/owner payments are linked when they happen to hold a till.
        $session = CashSession::currentFor($request->user());
        if (!$session && $request->user()->isCashier()) {
            return redirect()->route("cash-sessions.current")
                ->with("error", __("app.cash_session_required"));
        }

        $validated["user_id"] = $request->user()->id;
        $validated["cash_session_id"] = $session?->id;
        $validated["invoice_id"] = $order->invoice_id;
        $payment = Payment::create($validated);
        if ($payment->isCheque()) {
            app(Cheques::class)->attach($payment, $validated, $order->customer, $request->user());
        }

        $warning = $this->autoUpdateStatus($order, $request->user());
        if ($payment->method === "cash" && $session && $this->tillOverLimit($session)) {
            $warning = trim(($warning ? $warning . " " : "") . __("app.cash_over_limit"));
        }

        app(AutomaticNotifications::class)->paymentReceived($payment);

        $redirect = redirect()->route("orders.show", $order)
            ->with("success", $payment->change() > 0
                ? __("app.payment_recorded_change", ["change" => money($payment->change())])
                : __('app.payment_recorded'));

        if ($warning) {
            $redirect = $redirect->with("warning", $warning);
        }

        if ($payment->postToJournal() === null) {
            $redirect = $redirect->with("error", __("app.journal_skipped"));
        }

        return $redirect;
    }

    /** A4 receipt (HTML, or PDF with ?format=pdf); the thermal ticket is receipt(). */
    public function document(Payment $payment)
    {
        $payment->load(["order.customer", "order.payments", "cheque"]);

        return DocumentResponse::make("documents.payment", compact("payment"), __("app.receipt") . " REC-" . str_pad($payment->id, 6, "0", STR_PAD_LEFT));
    }

    public function receipt(Payment $payment)
    {
        $payment->load(["order.customer", "order.payments"]);

        return view("payments.receipt", compact("payment"));
    }

    public function update(PaymentRequest $request, Payment $payment)
    {
        if ($payment->isInClosedCashSession()) {
            return redirect()->route("orders.show", $payment->order_id)
                ->with("error", __("app.payment_cash_session_closed"));
        }

        if ($payment->order->status === "completed" && !$payment->order->is_credit) {
            return redirect()->route("orders.show", $payment->order_id)
                ->with("error", __('app.payment_edit_locked_error'));
        }

        Cheques::assertEditable($payment);

        $validated = $request->paymentData();

        $order = $payment->order;
        $paidOthers = Money::sum($order->payments()->whereKeyNot($payment->id)->pluck("amount"));
        $refunded = $order->refundedAmount();
        $max = Money::sub($order->netTotal(), Money::sub($paidOthers, $refunded));
        if (Money::compare($validated["amount"], $max) > 0 || Money::compare(Money::add($paidOthers, $validated["amount"]), $refunded) < 0) {
            return back()->withInput()
                ->withErrors(["amount" => __('app.payment_exceeds_remaining', [
                    "amount" => money($max),
                ])]);
        }

        $payment->update($validated);
        app(Cheques::class)->syncAfterUpdate($payment->fresh(), $validated, $request->user());

        // Auto-update status after payment edit
        $this->autoUpdateStatus($payment->order, $request->user());

        $payment->postToJournal();

        return redirect()->route("orders.show", $payment->order_id)
            ->with("success", __('app.payment_updated'));
    }

    public function destroy(Payment $payment)
    {
        if ($payment->isInClosedCashSession()) {
            return redirect()->route("orders.show", $payment->order_id)
                ->with("error", __("app.payment_cash_session_closed"));
        }

        if ($payment->order->status === "completed" && !$payment->order->is_credit) {
            return redirect()->route("orders.show", $payment->order_id)
                ->with("error", __('app.payment_delete_locked_error'));
        }

        if ($period = AccountingPeriod::closedOn($payment->paid_at)) {
            return redirect()->route("orders.show", $payment->order_id)
                ->with("error", __("app.period_closed", ["period" => $period->label()]));
        }

        // Money already handed back must stay covered by what was received.
        if (Money::compare($payment->order->refundedAmount(), Money::sub($payment->order->paidAmount(), $payment->amount)) > 0) {
            return redirect()->route("orders.show", $payment->order_id)
                ->with("error", __("app.payment_refunded_locked"));
        }

        if ($payment->cheque && !$payment->cheque->isInHand()) {
            return redirect()->route("orders.show", $payment->order_id)
                ->with("error", __("app.cheque_locked", ["status" => __("app.cheque_status_" . $payment->cheque->status)]));
        }

        $orderId = $payment->order_id;
        $cheque = $payment->cheque;
        JournalEntry::forget("payment", $payment->id);
        $payment->delete();
        if ($cheque) {
            app(Cheques::class)->refreshOrDelete($cheque);
        }

        return redirect()->route("orders.show", $orderId)
            ->with("success", __('app.payment_deleted'));
    }

    /**
     * Auto-update order status: a ready order that is fully paid moves to delivering.
     * Orders paid in advance stay in production (they still have to be washed).
     * Returns a material-consumption warning message when one applies.
     */
    /** The till holds more cash than the business allows: time to take some to the safe. */
    private function tillOverLimit(CashSession $session): bool
    {
        $limit = (float) SiteSetting::get("cash_till_limit", 0);

        return $limit > 0 && $session->expectedCash() > $limit;
    }

    private function autoUpdateStatus(Order $order, ?User $user = null): ?string
    {
        return app(SettleOrderStatus::class)->handle($order, $user);
    }
}
