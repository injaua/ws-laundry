<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SettleOrderStatus;
use App\Models\AccountingPeriod;
use App\Models\CashSession;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Payments\Cheques;
use App\Services\WhatsApp\AutomaticNotifications;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            "order_id" => "required|exists:orders,id",
            "paid_at" => "required|date",
            "amount" => "required|numeric|min:1",
            "method" => "required|in:" . implode(",", Payment::METHODS),
            "note" => "nullable|string|max:255",
        ] + Payment::referenceRules());
        $validated = Payment::validateReference($validated);

        AccountingPeriod::assertOpen($validated["paid_at"], "paid_at");

        $order = Order::with("customer")->findOrFail($validated["order_id"]);
        Cheques::assertAccepted($order->customer, $validated["method"]);
        // A completed order is closed for payments, except credit orders: those are
        // exactly the ones that settle after delivery.
        if ($order->status === "completed" && !$order->is_credit) {
            return redirect()->route("orders.show", $order)
                ->with("error", __('app.payment_locked_error'));
        }

        $remaining = $order->remainingBalance(); // after credit notes and refunds
        if ($remaining <= 0) {
            return redirect()->route("orders.show", $order)
                ->with("error", __('app.order_fully_paid'));
        }
        if ((float) $validated["amount"] > $remaining) {
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

    public function receipt(Payment $payment)
    {
        $payment->load(["order.customer", "order.payments"]);

        return view("payments.receipt", compact("payment"));
    }

    public function update(Request $request, Payment $payment)
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

        $validated = $request->validate([
            "paid_at" => "required|date",
            "amount" => "required|numeric|min:1",
            "method" => "required|in:" . implode(",", Payment::METHODS),
            "note" => "nullable|string|max:255",
        ] + Payment::referenceRules());
        if ($validated["method"] === "cheque" && !$payment->isCheque()) {
            Cheques::assertAccepted($payment->order?->customer, "cheque");
        }
        // Payments of a grouped receipt keep sharing their reference.
        if ($payment->group_ref && in_array($validated["method"], Payment::UNIQUE_REF_METHODS, true)
            && Payment::normalizeRef($validated["transaction_ref"] ?? null) === $payment->transaction_ref) {
            $validated["transaction_ref"] = $payment->transaction_ref;
        } else {
            $validated = Payment::validateReference($validated, $payment->id);
        }

        AccountingPeriod::assertOpen($payment->paid_at, "paid_at");
        AccountingPeriod::assertOpen($validated["paid_at"], "paid_at");

        $order = $payment->order;
        $paidOthers = (float) $order->payments()->whereKeyNot($payment->id)->sum("amount");
        $max = round($order->netTotal() - ($paidOthers - $order->refundedAmount()), 2);
        if ((float) $validated["amount"] > $max || $paidOthers + (float) $validated["amount"] < $order->refundedAmount() - 0.004) {
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
        if ($payment->order->refundedAmount() > $payment->order->paidAmount() - (float) $payment->amount + 0.004) {
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
