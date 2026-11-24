<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordGroupedPayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Invoicing;
use App\Support\Documents\DocumentResponse;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(private Invoicing $invoicing) {}

    /** Filters of the invoice list: the first two list both kinds of invoice, the others consolidated invoices only. */
    public const LIST_FILTERS = ["unpaid", "overdue", "draft", "paid", "void", "all"];

    /**
     * Unpaid invoices by default, of both kinds in one list, oldest first: the invoice of
     * each order still owing (unless it is billed on an issued consolidated invoice) and
     * the issued consolidated invoices (FT) with a balance.
     */
    public function index(Request $request)
    {
        Gate::authorize("viewAny", Invoice::class);

        $filter = in_array($request->status, self::LIST_FILTERS, true) ? $request->status : "unpaid";
        $customerId = $request->integer("customer_id") ?: null;
        $kind = in_array($request->kind, ["order", "invoice"], true) ? $request->kind : null;
        $unpaid = in_array($filter, ["unpaid", "overdue"], true);

        $rows = collect();

        if ($kind !== "invoice" && $unpaid) {
            $orders = Order::outstanding()
                ->with(["customer", "payments", "creditNotes"])
                ->where(fn ($q) => $q->whereNull("invoice_id")->orWhereHas("invoice", fn ($i) => $i->where("status", "!=", "issued")))
                ->when($customerId, fn ($q) => $q->where("customer_id", $customerId))
                ->when($filter === "overdue", fn ($q) => $q->whereDate("due_date", "<", today()->toDateString()))
                ->get();
            $rows = $rows->concat($orders->map(fn (Order $order) => $this->orderRow($order)));
        }

        if ($kind !== "order") {
            $invoices = Invoice::with(["customer", "orders.payments", "orders.creditNotes"])
                ->when($customerId, fn ($q) => $q->where("customer_id", $customerId))
                ->when($unpaid, fn ($q) => $q->issued()->whereHas("orders", fn ($o) => $o->outstanding()))
                ->when($filter === "overdue", fn ($q) => $q->whereDate("due_date", "<", today()->toDateString()))
                ->when(in_array($filter, ["draft", "void"], true), fn ($q) => $q->where("status", $filter))
                ->when($filter === "paid", fn ($q) => $q->issued()->whereDoesntHave("orders", fn ($o) => $o->outstanding()))
                ->get();
            $rows = $rows->concat($invoices->map(fn (Invoice $invoice) => $this->invoiceRow($invoice)));
        }

        // Unpaid: what falls due first comes first. Otherwise the latest documents first.
        $rows = $unpaid
            ? $rows->sortBy(fn ($r) => ($r["due_date"] ?? $r["date"])?->format("Ymd") . sprintf("%010d", $r["id"]))->values()
            : $rows->sortByDesc(fn ($r) => $r["date"]?->format("Ymd") . sprintf("%010d", $r["id"]))->values();

        $totalOutstanding = $unpaid ? Money::sum($rows->pluck("balance")) : null;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, [
            "path" => $request->url(),
            "query" => $request->query(),
        ]);
        $customers = Customer::orderBy("name")->get(["id", "name"]);

        return view("invoices.index", [
            "rows" => $paginated,
            "customers" => $customers,
            "filter" => $filter,
            "kind" => $kind,
            "totalOutstanding" => $totalOutstanding,
        ]);
    }

    /** The invoice of one order, as a row of the invoice list. */
    private function orderRow(Order $order): array
    {
        $overdue = $order->due_date !== null && $order->due_date->lt(today());

        return [
            "id" => $order->id,
            "kind" => "order",
            "number" => $order->order_number,
            "url" => route("orders.show", $order),
            "print_url" => route("orders.document", $order),
            "pay_url" => route("orders.payment", $order),
            "customer" => $order->customer?->name,
            "date" => $order->received_at,
            "due_date" => $order->due_date,
            "total" => $order->netTotal(),
            "balance" => Money::max(0, $order->remainingBalance()),
            "overdue" => $overdue,
            "status" => $overdue ? "overdue" : "unpaid",
            "color" => $overdue ? "danger" : "warning",
        ];
    }

    /** A consolidated invoice (FT), as a row of the invoice list. */
    private function invoiceRow(Invoice $invoice): array
    {
        return [
            "id" => $invoice->id,
            "kind" => "invoice",
            "number" => $invoice->label(),
            "url" => route("invoices.show", $invoice),
            "print_url" => $invoice->isDraft() ? route("invoices.print", [$invoice, "preview" => 1]) : route("invoices.print", $invoice),
            "pay_url" => $invoice->isIssued() && !$invoice->isPaid() ? route("invoices.show", $invoice) : null,
            "customer" => $invoice->customer?->name,
            "date" => $invoice->issue_date ?? $invoice->created_at,
            "due_date" => $invoice->due_date,
            "total" => (float) $invoice->total,
            "balance" => $invoice->isVoid() ? null : $invoice->balance(),
            "overdue" => $invoice->isOverdue(),
            "status" => $invoice->displayStatus(),
            "color" => $invoice->statusColor(),
        ];
    }

    /** Pick customer and period, then tick the orders to bill. */
    public function create(Request $request)
    {
        Gate::authorize("create", Invoice::class);

        $customers = Customer::where("is_credit_customer", true)->orderBy("name")->get();
        $customer = $request->integer("customer_id") ? Customer::find($request->integer("customer_id")) : null;

        $start = request_date("period_start", today()->subMonthNoOverflow()->startOfMonth());
        $end = request_date("period_end", $start->copy()->endOfMonth());

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

        return DocumentResponse::make("invoices.print", compact("invoice"), __("app.invoice") . " " . $invoice->label());
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
            "method" => ["required", Rule::in(Payment::acceptedMethods())],
            "note" => "nullable|string|max:255",
        ] + Payment::referenceRules());
        $data = Payment::validateReference($data);

        $result = $recorder->handle($invoice->customer, $data, $request->user(), $invoice);

        $redirect = redirect()->route("invoices.show", $invoice)
            ->with("success", __("app.grouped_payment_recorded", ["ref" => $result["group_ref"], "count" => $result["payments"]->count()])
                . (($change = $result["payments"]->last()?->change()) > 0 ? " " . __("app.change_to_give", ["change" => money($change)]) : ""));

        return $result["journal_skipped"] ? $redirect->with("error", __("app.journal_skipped")) : $redirect;
    }
}
