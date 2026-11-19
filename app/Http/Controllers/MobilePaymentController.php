<?php

namespace App\Http\Controllers;

use App\Models\CourierJob;
use App\Models\CreditNote;
use App\Models\MessageTemplate;
use App\Models\MobileTransaction;
use App\Models\Order;
use App\Services\MobileMoney\MobileMoneySettings;
use App\Services\MobileMoney\MobilePayments;
use App\Services\WhatsApp\AutomaticNotifications;
use App\Services\WhatsApp\Messenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Push payments (M-Pesa / e-Mola) from the counter or the courier, refunds and payment links. */
class MobilePaymentController extends Controller
{
    public function __construct(private MobilePayments $payments) {}

    public function index(Request $request): View
    {
        abort_if($request->user()->isCourier(), 403);

        $from = $request->filled("from") ? Carbon::parse($request->input("from")) : today()->subDays(6);
        $to = $request->filled("to") ? Carbon::parse($request->input("to")) : today();

        $query = MobileTransaction::with(["order:id,order_number", "customer:id,name", "user:id,name", "creditNote:id,number"])
            ->whereDate("created_at", ">=", $from->toDateString())->whereDate("created_at", "<=", $to->toDateString())
            ->when($request->filled("status"), fn ($q) => $q->where("status", $request->input("status")))
            ->when($request->filled("provider"), fn ($q) => $q->where("provider", $request->input("provider")))
            ->when($request->filled("type"), fn ($q) => $q->where("type", $request->input("type")));

        $summary = (clone $query)->get(["type", "status", "amount"]);

        return view("mobile-payments.index", [
            "transactions" => $query->latest("id")->paginate(25)->withQueryString(),
            "from" => $from,
            "to" => $to,
            "received" => (float) $summary->where("type", "c2b")->where("status", "success")->sum("amount"),
            "refunded" => (float) $summary->where("type", "b2c")->where("status", "success")->sum("amount"),
            "failed" => $summary->whereIn("status", ["failed", "expired"])->count(),
            "open" => $summary->whereIn("status", MobileTransaction::OPEN_STATUSES)->count(),
            "modes" => collect(MobileTransaction::PROVIDERS)->mapWithKeys(fn ($p) => [$p => MobileMoneySettings::mode($p)]),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            "order_id" => "required|integer|exists:orders,id",
            "provider" => ["required", Rule::in(MobileTransaction::PROVIDERS)],
            "phone" => "required|string|max:20",
            "amount" => "nullable|numeric|min:1",
            "courier_job_id" => "nullable|integer|exists:courier_jobs,id",
        ]);

        $order = Order::findOrFail($data["order_id"]);
        $job = !empty($data["courier_job_id"]) ? CourierJob::findOrFail($data["courier_job_id"]) : null;

        // Couriers charge only the order of their own delivery.
        if ($request->user()->isCourier()) {
            abort_unless($job && $job->courier_id === $request->user()->id && $job->order_id === $order->id && $job->isActionable(), 403);
        }

        $tx = $this->payments->request($order, $data["provider"], $data["phone"], isset($data["amount"]) ? (float) $data["amount"] : null,
            $request->user(), $job ? "courier" : "counter", $job);

        if ($request->expectsJson()) {
            return response()->json($this->statusPayload($tx), 201);
        }

        return redirect()->route("mobile-payments.show", $tx);
    }

    public function show(Request $request, MobileTransaction $transaction): View
    {
        $this->authorizeView($request, $transaction);

        return view("mobile-payments.show", ["tx" => $transaction->load(["order.customer", "payment", "creditNote", "user:id,name"])]);
    }

    public function status(Request $request, MobileTransaction $transaction): JsonResponse
    {
        $this->authorizeView($request, $transaction);

        return response()->json($this->statusPayload($transaction->fresh()));
    }

    public function refresh(Request $request, MobileTransaction $transaction): RedirectResponse
    {
        $this->authorizeView($request, $transaction);
        $this->payments->refresh($transaction);

        return back()->with("success", __("app.mm_status_checked"));
    }

    public function refund(Request $request, CreditNote $creditNote): RedirectResponse
    {
        Gate::authorize("refund", $creditNote);

        $data = $request->validate(["phone" => "required|string|max:20"]);
        $tx = $this->payments->refund($creditNote, $data["phone"], $request->user());

        return redirect()->route("mobile-payments.show", $tx)->with("success", __("app.mm_refund_sent"));
    }

    /** WhatsApp message with the order's payment link. */
    public function sendLink(Request $request, Order $order, Messenger $messenger, AutomaticNotifications $notifications): RedirectResponse
    {
        abort_if($request->user()->isCourier(), 403);

        if (MobileMoneySettings::enabledProviders() === []) {
            return back()->with("error", __("app.mm_provider_disabled"));
        }
        if ($order->remainingBalance() <= 0) {
            return back()->with("error", __("app.order_fully_paid"));
        }

        $template = MessageTemplate::where("name", "Pedido de pagamento")->where("is_active", true)->first();
        if (!$template) {
            return back()->with("error", __("app.mm_link_template_missing"));
        }

        try {
            $messenger->sendTemplate($template, $order->customer, $notifications->variables($order, [
                "amount" => money($order->remainingBalance()),
                "pay_link" => $order->payLink(),
            ]), $request->user(), $order, "pay_link:{$order->id}:" . now()->timestamp);
        } catch (Throwable $e) {
            return back()->with("error", $e->getMessage());
        }

        return back()->with("success", __("app.mm_link_sent"));
    }

    private function authorizeView(Request $request, MobileTransaction $tx): void
    {
        if ($request->user()->isCourier()) {
            abort_unless($tx->user_id === $request->user()->id, 403);
        }
    }

    private function statusPayload(MobileTransaction $tx): array
    {
        return [
            "id" => $tx->id,
            "reference" => $tx->reference,
            "status" => $tx->status,
            "status_label" => __("app.mm_status_" . $tx->status),
            "message" => $tx->response_message,
            "provider_ref" => $tx->provider_ref,
            "open" => $tx->isOpen(),
            "payment_id" => $tx->payment_id,
            "order_url" => $tx->order_id ? route("orders.show", $tx->order_id) : null,
        ];
    }
}
