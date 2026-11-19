<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\AutomaticNotifications;
use App\Services\WhatsApp\Messenger;
use App\Services\WhatsApp\WebSessionGateway;
use App\Services\WhatsApp\WhatsAppChannels;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * WhatsApp inbox: conversations with customers happen inside the platform, through
 * the WhatsApp Business API, instead of on the cashier's phone.
 */
class CustomerMessageController extends Controller
{
    public function __construct(private Messenger $messenger) {}

    public function index(Request $request, ?string $phone = null): View
    {
        $search = trim((string) $request->query("search"));
        $phone = $phone !== null ? $this->validPhone($phone) : null;

        $conversations = $this->conversations($search);
        $customers = $search !== ""
            ? Customer::whereNotNull("phone")->where("phone", "!=", "")
                ->where(fn ($q) => $q->where("name", "like", "%{$search}%")->orWhere("phone", "like", "%{$search}%"))
                ->orderBy("name")->limit(15)->get()
            : collect();

        $thread = null;
        if ($phone) {
            $thread = $this->thread($phone);
            WhatsAppMessage::unread()->where("phone", $phone)->update(["read_by_staff_at" => now()]);
        }

        $templates = MessageTemplate::where("is_active", true)->orderBy("name")->get();

        return view("messages.whatsapp", [
            "conversations" => $conversations,
            "customers" => $customers,
            "search" => $search,
            "phone" => $phone,
            "thread" => $thread,
            "templates" => $templates,
            "live" => $this->messenger->isLive(),
            "qrDisconnected" => $this->qrDisconnected(),
        ]);
    }

    /** New messages and status changes since the last poll. */
    public function poll(Request $request, string $phone): JsonResponse
    {
        $phone = $this->validPhone($phone);
        $since = $request->integer("since");

        $new = WhatsAppMessage::with("sender:id,name")->where("phone", $phone)->where("id", ">", $since)->orderBy("id")->get();
        WhatsAppMessage::unread()->where("phone", $phone)->update(["read_by_staff_at" => now()]);

        // Delivery ticks of the latest outbound messages may have changed.
        $statuses = WhatsAppMessage::where("phone", $phone)->where("direction", WhatsAppMessage::OUT)
            ->latest("id")->limit(30)->pluck("status", "id");

        return response()->json([
            "messages" => $new->map(fn ($m) => $this->present($m))->values(),
            "statuses" => $statuses,
            "canReply" => $this->messenger->canSendFreeText($phone),
        ]);
    }

    public function reply(Request $request, string $phone): RedirectResponse
    {
        $phone = $this->validPhone($phone);
        $data = $request->validate(["body" => "required|string|max:4096"]);

        try {
            $this->messenger->sendText($phone, $data["body"], $request->user(), Customer::findByWhatsapp($phone));
        } catch (WhatsAppException $e) {
            return back()->withInput()->with("error", $e->getMessage());
        }

        return redirect()->route("messages.clients.thread", $phone);
    }

    /** Send a template to a customer: starts a conversation even outside the 24h window. */
    public function sendTemplate(Request $request, AutomaticNotifications $notifications): RedirectResponse
    {
        $data = $request->validate([
            "customer_id" => "required|integer|exists:customers,id",
            "template_id" => "required|integer|exists:message_templates,id",
            "order_id" => "nullable|integer|exists:orders,id",
        ]);

        $customer = Customer::findOrFail($data["customer_id"]);
        $template = MessageTemplate::where("is_active", true)->findOrFail($data["template_id"]);
        $order = isset($data["order_id"]) ? $customer->orders()->find($data["order_id"]) : $customer->latestOrder;

        $vars = $order
            ? $notifications->variables($order)
            : ["customer" => $customer->name, "order" => "", "amount" => "", "due_date" => "", "delivery_date" => "", "days" => ""];

        try {
            $this->messenger->sendTemplate($template, $customer, $vars, $request->user(), $order);
        } catch (WhatsAppException $e) {
            return back()->with("error", $e->getMessage());
        }

        return redirect()->route("messages.clients.thread", $customer->normalizedWhatsapp())
            ->with("success", __("app.wa_message_queued"));
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(["count" => WhatsAppMessage::unread()->count()]);
    }

    /** One row per phone number, most recent conversation first. */
    private function conversations(string $search)
    {
        $latest = WhatsAppMessage::query()
            ->select("phone", DB::raw("MAX(id) as last_id"), DB::raw("SUM(CASE WHEN direction = 'in' AND read_by_staff_at IS NULL THEN 1 ELSE 0 END) as unread"))
            ->groupBy("phone");

        $rows = DB::query()->fromSub($latest, "c")->orderByDesc("last_id")->limit(100)->get();
        $last = WhatsAppMessage::with("customer:id,name")->whereIn("id", $rows->pluck("last_id"))->get()->keyBy("id");

        return $rows->map(function ($row) use ($last) {
            $message = $last[$row->last_id];
            $name = $message->customer?->name
                ?? WhatsAppMessage::where("phone", $row->phone)->whereNotNull("customer_id")->with("customer:id,name")->first()?->customer?->name
                ?? ($message->meta["profile_name"] ?? null);

            return (object) ["phone" => $row->phone, "name" => $name, "last" => $message, "unread" => (int) $row->unread];
        })->filter(fn ($c) => $search === "" || str_contains(mb_strtolower($c->name ?? ""), mb_strtolower($search)) || str_contains($c->phone, preg_replace("/\D+/", "", $search) ?: "\0"))
            ->values();
    }

    /** The QR-linked phone is not connected, so nothing can be sent right now. */
    private function qrDisconnected(): bool
    {
        $gateway = app(WhatsAppChannels::class)->conversations();

        return $gateway instanceof WebSessionGateway && $gateway->status()["status"] !== "open";
    }

    private function thread(string $phone): object
    {
        $messages = WhatsAppMessage::with(["sender:id,name", "template:id,name"])->where("phone", $phone)->orderBy("id")->get();
        $customer = $messages->firstWhere("customer_id", "!=", null)?->customer ?? Customer::findByWhatsapp($phone);
        $lastInbound = $messages->where("direction", WhatsAppMessage::IN)->max("created_at");

        return (object) [
            "phone" => $phone,
            "customer" => $customer,
            "messages" => $messages,
            "orders" => $customer ? $customer->orders()->latest()->limit(10)->get() : collect(),
            "profileName" => $messages->where("direction", WhatsAppMessage::IN)->last()?->meta["profile_name"] ?? null,
            "canReply" => $this->messenger->canSendFreeText($phone),
            // The 24h window and approved templates only matter on Meta's official API.
            "official" => $this->messenger->replyGateway($phone)->isOfficial(),
            "windowEndsAt" => $lastInbound ? $lastInbound->copy()->addHours((int) config("whatsapp.session_window_hours", 24)) : null,
        ];
    }

    public function present(WhatsAppMessage $m): array
    {
        return [
            "id" => $m->id,
            "direction" => $m->direction,
            "body" => $m->body,
            "type" => $m->type,
            "status" => $m->status,
            "error" => $m->error,
            "automatic" => $m->isAutomatic(),
            "sender" => $m->sender?->name,
            "at" => $m->created_at->format("d/m H:i"),
        ];
    }

    private function validPhone(string $phone): string
    {
        abort_unless(preg_match('/^\d{8,15}$/', $phone) === 1, 404);

        return $phone;
    }
}
