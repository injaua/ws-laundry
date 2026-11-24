<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordGroupedPayment;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\CustomerStatement;
use App\Support\Documents\DocumentResponse;
use App\Support\Documents\ReportDownload;
use App\Support\Documents\ReportTable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Business-customer account: grouped payments, statement of account and group receipts. */
class CustomerAccountController extends Controller
{
    public function pay(Request $request, Customer $customer, RecordGroupedPayment $recorder)
    {
        abort_unless($customer->is_credit_customer, 404);

        $data = $request->validate([
            "paid_at" => "required|date",
            "amount" => "required|numeric|min:1",
            "method" => ["required", Rule::in(Payment::acceptedMethods())],
            "note" => "nullable|string|max:255",
        ] + Payment::referenceRules());
        $data = Payment::validateReference($data);

        $result = $recorder->handle($customer, $data, $request->user());

        $redirect = redirect()->route("receivables.customer", $customer)
            ->with("success", __("app.grouped_payment_recorded", ["ref" => $result["group_ref"], "count" => $result["payments"]->count()])
                . (($change = $result["payments"]->last()?->change()) > 0 ? " " . __("app.change_to_give", ["change" => money($change)]) : ""));

        return $result["journal_skipped"] ? $redirect->with("error", __("app.journal_skipped")) : $redirect;
    }

    public function statement(Request $request, Customer $customer, CustomerStatement $statement)
    {
        abort_unless($customer->is_credit_customer, 404);

        $from = request_date("from", today()->startOfYear())->startOfDay();
        $to = request_date("to", today())->endOfDay();

        $data = $statement->build($customer, $from, $to);
        $openInvoices = $customer->invoices()->issued()->with("orders.payments", "orders.creditNotes")->orderBy("due_date")->get()
            ->filter(fn ($invoice) => $invoice->balance() > 0);

        $filename = __("app.statement_of_account") . " " . $customer->name . " " . $to->format("Y-m-d");
        if (ReportDownload::requested($request) === "xlsx") {
            return ReportDownload::make($this->statementTable($customer, $from, $to, $data, $filename), "xlsx");
        }

        return DocumentResponse::make(
            "customers.statement",
            compact("customer", "from", "to", "data", "openInvoices") + ["xlsxAvailable" => true],
            $filename,
        );
    }

    public function groupReceipt(string $groupRef)
    {
        $payments = Payment::where("group_ref", $groupRef)->with(["order.customer", "invoice", "user"])->orderBy("id")->get();
        abort_if($payments->isEmpty(), 404);

        $customer = $payments->first()->order->customer;

        return DocumentResponse::make("payments.group-receipt", compact("payments", "customer", "groupRef"), __("app.receipt") . " " . $groupRef);
    }

    private function statementTable(Customer $customer, Carbon $from, Carbon $to, array $data, string $filename): ReportTable
    {
        $table = new ReportTable(
            __("app.statement_of_account") . " — " . $customer->fiscalName(),
            $from->format(date_fmt()) . " – " . $to->format(date_fmt()),
            [
                [__("app.date"), "date"], [__("app.description"), "text"], [__("app.reference"), "text"], [__("app.invoice"), "text"],
                [__("app.debit"), "money"], [__("app.credit"), "money"], [__("app.balance"), "money"],
            ],
            $filename,
        );
        $table->summary(__("app.opening_balance"), $data["opening"])->summary(__("app.closing_balance"), $data["closing"]);

        foreach ($data["lines"] as $line) {
            $table->row([
                $line["date"]->format(date_fmt()),
                __("app.statement_" . $line["type"]) . ($line["method"] ? " (" . __("app." . $line["method"]) . ")" : ""),
                $line["reference"],
                $line["invoice"] ?? "",
                $line["debit"] > 0 ? $line["debit"] : null,
                $line["credit"] > 0 ? $line["credit"] : null,
                $line["balance"],
            ]);
        }

        return $table->row(["", __("app.closing_balance"), "", "", $data["debits"], $data["credits"], $data["closing"]], "total");
    }
}
