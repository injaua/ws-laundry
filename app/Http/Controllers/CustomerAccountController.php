<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordGroupedPayment;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\CustomerStatement;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Business-customer account: grouped payments, statement of account and group receipts. */
class CustomerAccountController extends Controller
{
    public function pay(Request $request, Customer $customer, RecordGroupedPayment $recorder)
    {
        abort_unless($customer->is_credit_customer, 404);

        $data = $request->validate([
            "paid_at" => "required|date",
            "amount" => "required|numeric|min:1",
            "method" => "required|in:" . implode(",", Payment::METHODS),
            "note" => "nullable|string|max:255",
        ] + Payment::referenceRules());
        $data = Payment::validateReference($data);

        $result = $recorder->handle($customer, $data, $request->user());

        $redirect = redirect()->route("receivables.customer", $customer)
            ->with("success", __("app.grouped_payment_recorded", ["ref" => $result["group_ref"], "count" => $result["payments"]->count()]));

        return $result["journal_skipped"] ? $redirect->with("error", __("app.journal_skipped")) : $redirect;
    }

    public function statement(Request $request, Customer $customer, CustomerStatement $statement)
    {
        abort_unless($customer->is_credit_customer, 404);

        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : today()->startOfYear();
        $to = $request->to ? Carbon::parse($request->to)->endOfDay() : today()->endOfDay();

        $data = $statement->build($customer, $from, $to);
        $openInvoices = $customer->invoices()->issued()->with("orders.payments", "orders.creditNotes")->orderBy("due_date")->get()
            ->filter(fn ($invoice) => $invoice->balance() > 0);

        return view("customers.statement", compact("customer", "from", "to", "data", "openInvoices"));
    }

    public function groupReceipt(string $groupRef)
    {
        $payments = Payment::where("group_ref", $groupRef)->with(["order.customer", "invoice", "user"])->orderBy("id")->get();
        abort_if($payments->isEmpty(), 404);

        $customer = $payments->first()->order->customer;

        return view("payments.group-receipt", compact("payments", "customer", "groupRef"));
    }
}
