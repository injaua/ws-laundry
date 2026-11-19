<?php

namespace App\Http\Controllers;

use App\Actions\CreditNotes\IssueCreditNote;
use App\Models\AccountingPeriod;
use App\Models\Complaint;
use App\Models\CreditNote;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Documents\DocumentResponse;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Notas de crédito and the refunds (devoluções) they settle. */
class CreditNoteController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize("viewAny", CreditNote::class);

        $type = $request->query("type");
        $from = $request->date("from") ?? now()->startOfMonth();
        $to = $request->date("to") ?? now();
        $search = trim((string) $request->query("search"));

        $notes = CreditNote::with(["order:id,order_number", "customer:id,name", "user:id,name"])
            ->whereDate("issued_at", ">=", $from->toDateString())->whereDate("issued_at", "<=", $to->toDateString())
            ->when(in_array($type, CreditNote::SETTLEMENTS, true), fn ($q) => $q->where("settlement", $type))
            ->when($search !== "", fn ($q) => $q->where(fn ($q) => $q->where("number", "like", "%{$search}%")
                ->orWhereHas("order", fn ($q) => $q->where("order_number", "like", "%{$search}%"))
                ->orWhereHas("customer", fn ($q) => $q->where("name", "like", "%{$search}%"))))
            ->latest("id")
            ->paginate(25)->withQueryString();

        $valid = CreditNote::valid()->whereDate("issued_at", ">=", $from->toDateString())->whereDate("issued_at", "<=", $to->toDateString());

        return view("credit-notes.index", [
            "notes" => $notes,
            "type" => $type,
            "from" => $from,
            "to" => $to,
            "search" => $search,
            "totals" => [
                "refund" => Money::round((clone $valid)->where("settlement", "refund")->sum("amount")),
                "deduct" => Money::round((clone $valid)->where("settlement", "deduct")->sum("amount")),
                "vat" => Money::round((clone $valid)->sum("vat_amount")),
            ],
        ]);
    }

    public function create(Request $request, Order $order): View
    {
        Gate::authorize("create", CreditNote::class);

        $complaint = $request->integer("complaint") ? Complaint::where("order_id", $order->id)->find($request->integer("complaint")) : null;

        return view("credit-notes.create", [
            "order" => $order->load("customer", "items.service", "items.package"),
            "complaint" => $complaint,
            "creditable" => $order->netTotal(),
            "refundable" => $order->netPaid(),
            "owed" => $order->remainingBalance(),
            "methods" => Payment::refundMethods(),
        ]);
    }

    public function store(Request $request, Order $order, IssueCreditNote $issue): RedirectResponse
    {
        Gate::authorize("create", CreditNote::class);

        $data = $request->validate([
            "amount" => "required|numeric|min:1",
            "reason" => ["required", Rule::in(CreditNote::REASONS)],
            "description" => "required|string|max:1000",
            "settlement" => ["required", Rule::in(CreditNote::SETTLEMENTS)],
            "refund_method" => ["nullable", "required_if:settlement,refund", Rule::in(Payment::refundMethods())],
            "issued_at" => "nullable|date|before_or_equal:today",
            "complaint_id" => ["nullable", "integer", Rule::exists("complaints", "id")->where("order_id", $order->id)],
        ]);

        $result = $issue->handle($order, $data, $request->user());

        $redirect = redirect()->route("orders.show", $order)
            ->with("success", __("app.cn_issued", ["number" => $result["note"]->number]))
            ->with("print_credit_note", $result["note"]->id);

        return $result["warning"] ? $redirect->with("warning", $result["warning"]) : $redirect;
    }

    /** Printable document (thermal ticket, like the invoice). */
    /** A4 credit note (HTML, or PDF with ?format=pdf). */
    public function document(CreditNote $creditNote)
    {
        Gate::authorize("view", $creditNote);

        return DocumentResponse::make(
            "documents.credit-note",
            ["note" => $creditNote->load("order.customer", "user", "voider")],
            __("app.credit_note") . " " . $creditNote->number,
        );
    }

    public function show(CreditNote $creditNote): View
    {
        Gate::authorize("view", $creditNote);

        return view("credit-notes.show", ["note" => $creditNote->load("order.customer", "user", "voider", "complaint")]);
    }

    /**
     * A mistaken credit note is voided, never deleted: its number stays in the sequence.
     * Its journal entry is removed while the period is open; a cash refund can only be
     * voided while the till it came out of is still open.
     */
    public function void(Request $request, CreditNote $creditNote): RedirectResponse
    {
        Gate::authorize("void", $creditNote);

        $data = $request->validate(["void_reason" => "required|string|max:255"]);

        if ($period = AccountingPeriod::closedOn($creditNote->issued_at)) {
            return back()->with("error", __("app.period_closed", ["period" => $period->label()]));
        }
        if ($creditNote->cash_session_id && $creditNote->cashSession && !$creditNote->cashSession->isOpen()) {
            return back()->with("error", __("app.cn_till_closed"));
        }
        // A note issued before a credit sale was delivered already lowered the revenue
        // booked on delivery; undoing it now would leave the books out of balance.
        if ($creditNote->order->is_credit && $creditNote->order->status === "completed" && !$creditNote->reverses_revenue) {
            return back()->with("error", __("app.cn_void_after_delivery"));
        }
        // Undoing a deduction makes the amount owed again; that is only possible while
        // the order still accepts it (paid-off orders cannot owe more than they paid).
        if (!$creditNote->isRefund() && $creditNote->order->status === "completed" && !$creditNote->order->is_credit) {
            return back()->with("error", __("app.cn_void_completed"));
        }

        $creditNote->update([
            "voided_at" => now(),
            "voided_by" => $request->user()->id,
            "void_reason" => $data["void_reason"],
        ]);
        JournalEntry::forget("credit_note", $creditNote->id);

        return redirect()->route("orders.show", $creditNote->order_id)
            ->with("success", __("app.cn_voided", ["number" => $creditNote->number]));
    }
}
