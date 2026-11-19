<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordGroupedPayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Invoicing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function __construct(private Invoicing $invoicing) {}

    public function index(Request $request)
    {
        Gate::authorize("viewAny", Invoice::class);

        $query = Invoice::with(["customer", "orders.payments", "orders.creditNotes"])->latest("id");

        if ($request->integer("customer_id")) {
            $query->where("customer_id", $request->integer("customer_id"));
        }

        if (in_array($request->status, Invoice::STATUSES, true)) {
            $query->where("status", $request->status);
        }

        $invoices = $query->paginate(20)->withQueryString();
        $customers = Customer::where("is_credit_customer", true)->orderBy("name")->get();

        return view("invoices.index", compact("invoices", "customers"));
    }

    /** Pick customer and period, then tick the orders to bill. */
    public function create(Request $request)
    {
        Gate::authorize("create", Invoice::class);

        $customers = Customer::where("is_credit_customer", true)->orderBy("name")->get();
        $customer = $request->integer("customer_id") ? Customer::find($request->integer("customer_id")) : null;

        $start = $request->period_start ? Carbon::parse($request->period_start) : today()->subMonthNoOverflow()->startOfMonth();
        $end = $request->period_end ? Carbon::parse($request->period_end) : $start->copy()->endOfMonth();

        $orders = $customer?->is_credit_customer
            ? $this->invoicing->eligibleOrders($customer, $end)->load(["payments", "creditNotes"])
            : collect();

        return view("invoices.create", compact("customers", "customer", "start", "end", "orders"));
    }

    public function store(Request $request)
    {
        Gate::authorize("create", Invoice::class);

        $validated = $request->validate([
            "customer_id" => "required|exists:customers,id",
            "period_start" => "required|date",
            "period_end" => "required|date|after_or_equal:period_start",
            "orders" => "required|array|min:1",
            "orders.*" => "integer",
            "notes" => "nullable|string|max:1000",
        ]);

        $invoice = $this->invoicing->createDraft(
            Customer::findOrFail($validated["customer_id"]),
            Carbon::parse($validated["period_start"]),
            Carbon::parse($validated["period_end"]),
            $validated["orders"],
            $request->user(),
            $validated["notes"] ?? null,
        );

        return redirect()->route("invoices.show", $invoice)->with("success", __("app.invoice_draft_created"));
    }

    public function show(Invoice $invoice)
    {
        Gate::authorize("view", $invoice);

        $invoice->load(["customer", "orders.payments", "orders.creditNotes", "payments.user", "creator"]);
        $groups = $invoice->payments->groupBy(fn (Payment $p) => $p->group_ref ?? "single-" . $p->id);

        return view("invoices.show", compact("invoice", "groups"));
    }

    public function print(Invoice $invoice)
    {
        Gate::authorize("view", $invoice);

        abort_if($invoice->isDraft() && !request()->boolean("preview"), 404);

        $invoice->load(["customer", "orders.items.service", "orders.items.package", "orders.payments", "orders.creditNotes"]);

        return view("invoices.print", compact("invoice"));
    }

    public function issue(Invoice $invoice)
    {
        Gate::authorize("update", $invoice);

        $this->invoicing->issue($invoice);

        return redirect()->route("invoices.show", $invoice)
            ->with("success", __("app.invoice_issued", ["number" => $invoice->number]));
    }

    public function removeOrder(Invoice $invoice, Order $order)
    {
        Gate::authorize("update", $invoice);

        $this->invoicing->removeOrder($invoice, $order);

        if ($invoice->orders()->doesntExist()) {
            $this->invoicing->void($invoice, "empty");

            return redirect()->route("invoices.index")->with("success", __("app.invoice_draft_discarded"));
        }

        return redirect()->route("invoices.show", $invoice)->with("success", __("app.invoice_order_removed"));
    }

    public function void(Request $request, Invoice $invoice)
    {
        Gate::authorize("void", $invoice);

        $wasDraft = $invoice->isDraft();
        $validated = $request->validate(["void_reason" => ($wasDraft ? "nullable" : "required") . "|string|max:255"]);

        if ($invoice->isIssued() && $invoice->payments()->exists()) {
            return redirect()->route("invoices.show", $invoice)->with("error", __("app.invoice_void_has_payments"));
        }

        $this->invoicing->void($invoice, $validated["void_reason"] ?? "draft discarded");

        return $wasDraft
            ? redirect()->route("invoices.index")->with("success", __("app.invoice_draft_discarded"))
            : redirect()->route("invoices.show", $invoice)->with("success", __("app.invoice_voided"));
    }

    public function pay(Request $request, Invoice $invoice, RecordGroupedPayment $recorder)
    {
        Gate::authorize("pay", $invoice);

        $data = $request->validate([
            "paid_at" => "required|date",
            "amount" => "required|numeric|min:1",
            "method" => "required|in:" . implode(",", Payment::METHODS),
            "note" => "nullable|string|max:255",
        ] + Payment::referenceRules());
        $data = Payment::validateReference($data);

        $result = $recorder->handle($invoice->customer, $data, $request->user(), $invoice);

        $redirect = redirect()->route("invoices.show", $invoice)
            ->with("success", __("app.grouped_payment_recorded", ["ref" => $result["group_ref"], "count" => $result["payments"]->count()]));

        return $result["journal_skipped"] ? $redirect->with("error", __("app.journal_skipped")) : $redirect;
    }
}
