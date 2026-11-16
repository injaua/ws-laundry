<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
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
        ]);

        $order = Order::findOrFail($validated["order_id"]);
        if ($order->status === "completed") {
            return redirect()->route("orders.show", $order)
                ->with("error", __('app.payment_locked_error'));
        }

        $remaining = round((float) $order->total_price - (float) $order->payments()->sum("amount"), 2);
        if ($remaining <= 0) {
            return redirect()->route("orders.show", $order)
                ->with("error", __('app.order_fully_paid'));
        }
        if ((float) $validated["amount"] > $remaining) {
            return back()->withInput()
                ->withErrors(["amount" => __('app.payment_exceeds_remaining', [
                    "amount" => number_format($remaining, 0, ".", ","),
                ])]);
        }

        $validated["user_id"] = $request->user()->id;
        $payment = Payment::create($validated);

        $this->autoUpdateStatus($order);

        $redirect = redirect()->route("orders.show", $order)
            ->with("success", __('app.payment_recorded'));

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
        if ($payment->order->status === "completed") {
            return redirect()->route("orders.show", $payment->order_id)
                ->with("error", __('app.payment_edit_locked_error'));
        }

        $validated = $request->validate([
            "paid_at" => "required|date",
            "amount" => "required|numeric|min:1",
            "method" => "required|in:" . implode(",", Payment::METHODS),
            "note" => "nullable|string|max:255",
        ]);

        $order = $payment->order;
        $paidOthers = (float) $order->payments()->whereKeyNot($payment->id)->sum("amount");
        $max = round((float) $order->total_price - $paidOthers, 2);
        if ((float) $validated["amount"] > $max) {
            return back()->withInput()
                ->withErrors(["amount" => __('app.payment_exceeds_remaining', [
                    "amount" => number_format($max, 0, ".", ","),
                ])]);
        }

        $payment->update($validated);

        // Auto-update status after payment edit
        $this->autoUpdateStatus($payment->order);

        $payment->postToJournal();

        return redirect()->route("orders.show", $payment->order_id)
            ->with("success", __('app.payment_updated'));
    }

    public function destroy(Payment $payment)
    {
        if ($payment->order->status === "completed") {
            return redirect()->route("orders.show", $payment->order_id)
                ->with("error", __('app.payment_delete_locked_error'));
        }

        $orderId = $payment->order_id;
        JournalEntry::forget("payment", $payment->id);
        $payment->delete();

        return redirect()->route("orders.show", $orderId)
            ->with("success", __('app.payment_deleted'));
    }

    /**
     * Auto-update order status: if fully paid, advance forward to delivering.
     */
    private function autoUpdateStatus(Order $order)
    {
        $totalPaid = $order->payments()->sum("amount");
        if ($totalPaid >= $order->total_price && $order->statusRank() < $order->statusRank("delivering")) {
            $order->update(["status" => "delivering"]);
        }
    }
}
