<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\WhatsAppMessage;
use App\Support\CurrentStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp webhooks: customer messages arrive here, as do the sent / delivered /
 * read / failed updates for the messages we sent. Two senders, same payload shape:
 * Meta's Cloud API (signed with the app secret) and the QR-code companion service
 * in whatsapp-web/ (signed with WHATSAPP_WEB_TOKEN).
 */
class WhatsAppWebhookController extends Controller
{
    /** Subscription handshake when the webhook URL is registered in Meta. */
    public function verify(Request $request): Response
    {
        $token = config("whatsapp.cloud.verify_token");

        if ($request->query("hub_mode") === "subscribe" && filled($token)
            && hash_equals((string) $token, (string) $request->query("hub_verify_token"))) {
            return response((string) $request->query("hub_challenge"), 200)->header("Content-Type", "text/plain");
        }

        return response("Forbidden", 403);
    }

    public function receive(Request $request): Response
    {
        if (!$this->signatureIsValid($request)) {
            Log::warning("[whatsapp] webhook rejected: invalid signature");

            return response("Invalid signature", 401);
        }

        return $this->process($request, "cloud");
    }

    /** From the QR-code service. Always signed: it runs on our own server with our secret. */
    public function receiveWeb(Request $request): Response
    {
        $secret = (string) config("whatsapp.web.token");
        $expected = "sha256=" . hash_hmac("sha256", $request->getContent(), $secret);

        if (strlen($secret) < 16 || !hash_equals($expected, (string) $request->header("X-Signature-256"))) {
            Log::warning("[whatsapp] QR webhook rejected: invalid signature");

            return response("Invalid signature", 401);
        }

        return $this->process($request, "web");
    }

    private function process(Request $request, string $channel): Response
    {
        foreach ($request->input("entry", []) as $entry) {
            foreach ($entry["changes"] ?? [] as $change) {
                $value = $change["value"] ?? [];

                foreach ($value["messages"] ?? [] as $message) {
                    $this->inStoreOf($message, fn () => $this->storeInbound($message, $value["contacts"] ?? [], $channel));
                }

                // Only the QR service sends these: messages typed on the linked phone itself.
                if ($channel === "web") {
                    foreach ($value["echoes"] ?? [] as $message) {
                        $this->inStoreOf($message, fn () => $this->storeFromPhone($message));
                    }
                }

                foreach ($value["statuses"] ?? [] as $status) {
                    $this->applyStatus($status);
                }
            }
        }

        // Always 200 once authenticated, or Meta keeps retrying the same payload.
        return response("OK", 200);
    }

    private function signatureIsValid(Request $request): bool
    {
        $secret = config("whatsapp.cloud.app_secret");

        if (blank($secret)) {
            // Unsigned calls are only trusted on a developer machine or in tests;
            // anywhere else anyone could inject fake customer messages.
            return app()->environment("local", "testing");
        }

        $expected = "sha256=" . hash_hmac("sha256", $request->getContent(), $secret);

        return hash_equals($expected, (string) $request->header("X-Hub-Signature-256"));
    }

    /**
     * One WhatsApp number serves every store: a message joins the conversation of the store
     * that last talked to that number; else the store of the customer's latest order; else
     * the main store.
     */
    private function inStoreOf(array $message, \Closure $callback): void
    {
        $phone = preg_replace("/\D+/", "", (string) ($message["from"] ?? ""));
        CurrentStore::run($phone === "" ? Store::defaultId() : $this->storeForPhone($phone), $callback);
    }

    private function storeForPhone(string $phone): ?int
    {
        $last = WhatsAppMessage::acrossStores()->where("phone", $phone)->latest("id")->value("store_id");
        if ($last) {
            return (int) $last;
        }

        $customers = CurrentStore::each(fn () => Customer::findByWhatsapp($phone));
        $latest = collect($customers)->filter()
            ->sortByDesc(fn (Customer $customer) => Order::acrossStores()->where("customer_id", $customer->id)->max("id") ?? 0)
            ->first();

        return $latest?->store_id ?? Store::defaultId();
    }

    private function storeInbound(array $message, array $contacts, string $channel): void
    {
        $providerId = $message["id"] ?? null;
        $phone = preg_replace("/\D+/", "", (string) ($message["from"] ?? ""));

        if (!$providerId || $phone === "" || WhatsAppMessage::where("provider_message_id", $providerId)->exists()) {
            return; // Meta may deliver the same message more than once
        }

        $type = $message["type"] ?? "text";
        $body = $this->bodyOf($message);

        $customer = Customer::findByWhatsapp($phone);

        WhatsAppMessage::create([
            "channel" => $channel,
            "phone" => $phone,
            "customer_id" => $customer?->id,
            "direction" => WhatsAppMessage::IN,
            "type" => $type,
            "body" => $body,
            "status" => "received",
            "provider_message_id" => $providerId,
            "meta" => array_filter([
                "profile_name" => collect($contacts)->firstWhere("wa_id", $message["from"] ?? null)["profile"]["name"] ?? null,
                "media_id" => $message[$type]["id"] ?? null,
                "mime_type" => $message[$type]["mime_type"] ?? null,
            ]),
            "status_at" => now(),
        ]);

        // "PARAR" / "STOP": the customer no longer receives automatic notifications.
        if ($customer && in_array(mb_strtoupper(trim((string) $body)), config("whatsapp.opt_out_keywords", []), true)) {
            $customer->update(["whatsapp_opt_out" => true]);
        }
    }

    /**
     * A reply typed on the phone linked by QR code: stored as an outgoing message of the
     * conversation (no staff user), and the customer's earlier messages count as read.
     */
    private function storeFromPhone(array $message): void
    {
        $providerId = $message["id"] ?? null;
        $phone = preg_replace("/\D+/", "", (string) ($message["from"] ?? ""));

        if (!$providerId || $phone === "" || WhatsAppMessage::where("provider_message_id", $providerId)->exists()) {
            return;
        }

        $customer = Customer::findByWhatsapp($phone);
        $type = $message["type"] ?? "text";

        WhatsAppMessage::create([
            "channel" => "web",
            "phone" => $phone,
            "customer_id" => $customer?->id,
            "direction" => WhatsAppMessage::OUT,
            "type" => $type,
            "body" => $this->bodyOf($message),
            "status" => "sent",
            "provider_message_id" => $providerId,
            "meta" => array_filter(["source" => "phone", "mime_type" => $message[$type]["mime_type"] ?? null]),
            "status_at" => now(),
        ]);

        WhatsAppMessage::unread()->where("phone", $phone)->update(["read_by_staff_at" => now()]);
    }

    private function bodyOf(array $message): ?string
    {
        $type = $message["type"] ?? "text";

        return match ($type) {
            "text" => $message["text"]["body"] ?? "",
            "button" => $message["button"]["text"] ?? "",
            "interactive" => $message["interactive"]["button_reply"]["title"] ?? $message["interactive"]["list_reply"]["title"] ?? "",
            "image", "video", "document", "audio", "sticker" => $message[$type]["caption"] ?? null,
            "location" => trim(($message["location"]["name"] ?? "") . " " . ($message["location"]["address"] ?? "")),
            default => null,
        };
    }

    private function applyStatus(array $status): void
    {
        $message = WhatsAppMessage::acrossStores()->where("provider_message_id", $status["id"] ?? "")->first();

        if (!$message) {
            return;
        }

        $error = isset($status["errors"][0])
            ? trim(($status["errors"][0]["code"] ?? "") . " " . ($status["errors"][0]["title"] ?? ""))
            : null;

        $message->applyStatus((string) ($status["status"] ?? ""), $error);
    }
}
