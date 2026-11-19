<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Support\CurrentStore;
use App\Support\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp webhooks: customer messages arrive here, as do the sent / delivered /
 * read / failed updates for the messages we sent. Two senders, same payload shape:
 * Meta's Cloud API (signed with the app secret) and the QR-code companion service
 * in whatsapp-web/ (signed with WHATSAPP_WEB_TOKEN).
 *
 * The company number serves every store that uses the company WhatsApp configuration; a store
 * with its own configuration has its own number: messages to it (its official API phone number
 * id, or its QR-code session) always go to that store.
 */
class WhatsAppWebhookController extends Controller
{
    /** Subscription handshake when the webhook URL is registered in Meta. */
    public function verify(Request $request): Response
    {
        $given = (string) $request->query("hub_verify_token");

        // Each number (the company's, or a store's own) may have its own verify token.
        foreach (WhatsAppSettings::allLevels() as $config) {
            $token = (string) ($config["cloud"]["verify_token"] ?? "");
            if ($request->query("hub_mode") === "subscribe" && $token !== "" && hash_equals($token, $given)) {
                return response((string) $request->query("hub_challenge"), 200)->header("Content-Type", "text/plain");
            }
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
                $stores = $channel === "web"
                    ? $this->storesOfSession((string) $request->input("session", WhatsAppSettings::COMPANY_SESSION))
                    : $this->storesOfCloudNumber((string) ($value["metadata"]["phone_number_id"] ?? ""));

                foreach ($value["messages"] ?? [] as $message) {
                    $this->inStoreOf($message, $stores, fn () => $this->storeInbound($message, $value["contacts"] ?? [], $channel));
                }

                // Only the QR service sends these: messages typed on the linked phone itself.
                if ($channel === "web") {
                    foreach ($value["echoes"] ?? [] as $message) {
                        $this->inStoreOf($message, $stores, fn () => $this->storeFromPhone($message));
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

    /** Signed with the app secret of one of our Meta apps (the company's, or a store's own). */
    private function signatureIsValid(Request $request): bool
    {
        $secrets = collect(WhatsAppSettings::allLevels())
            ->map(fn (array $config) => (string) ($config["cloud"]["app_secret"] ?? ""))
            ->filter()->unique();

        if ($secrets->isEmpty()) {
            // Unsigned calls are only trusted on a developer machine or in tests;
            // anywhere else anyone could inject fake customer messages.
            return app()->environment("local", "testing");
        }

        $given = (string) $request->header("X-Hub-Signature-256");

        return $secrets->contains(fn (string $secret) => hash_equals("sha256=" . hash_hmac("sha256", $request->getContent(), $secret), $given));
    }

    /** @return int[] stores using the company WhatsApp number (every active store when all have their own) */
    private function companyStores(): array
    {
        $own = StoreSettings::owners("whatsapp");
        $stores = Store::active()->orderBy("id")->pluck("id")->map(fn ($id) => (int) $id);

        return $stores->diff($own)->values()->all() ?: $stores->all();
    }

    /** @return int[] the store of a QR-code session, or the stores using the company number */
    private function storesOfSession(string $session): array
    {
        $storeId = WhatsAppSettings::storeOfSession($session);

        return $storeId && Store::whereKey($storeId)->exists() ? [$storeId] : $this->companyStores();
    }

    /** @return int[] the stores served by the official API number that received the message */
    private function storesOfCloudNumber(string $phoneNumberId): array
    {
        if ($phoneNumberId === "") {
            return $this->companyStores();
        }

        $stores = [];
        foreach (WhatsAppSettings::allLevels() as $level => $config) {
            if ((string) ($config["cloud"]["phone_number_id"] ?? "") === $phoneNumberId) {
                $stores = [...$stores, ...($level === "" ? $this->companyStores() : [(int) $level])];
            }
        }

        return array_values(array_unique($stores)) ?: $this->companyStores();
    }

    /**
     * When a number serves several stores, a message joins the conversation of the store that last
     * talked to that customer; else the store of the customer's latest order; else the main store.
     *
     * @param  int[]  $stores  the stores this number serves
     */
    private function inStoreOf(array $message, array $stores, \Closure $callback): void
    {
        $phone = preg_replace("/\D+/", "", (string) ($message["from"] ?? ""));
        $storeId = count($stores) <= 1 || $phone === ""
            ? ($stores[0] ?? Store::defaultId())
            : $this->storeForPhone($phone, $stores);

        CurrentStore::run($storeId, $callback);
    }

    /** @param  int[]  $stores */
    private function storeForPhone(string $phone, array $stores): ?int
    {
        $last = WhatsAppMessage::acrossStores()->where("phone", $phone)->whereIn("store_id", $stores)->latest("id")->value("store_id");
        if ($last) {
            return (int) $last;
        }

        $customers = array_map(fn (int $storeId) => CurrentStore::run($storeId, fn () => Customer::findByWhatsapp($phone)), $stores);
        $latest = collect($customers)->filter()
            ->sortByDesc(fn (Customer $customer) => Order::acrossStores()->where("customer_id", $customer->id)->max("id") ?? 0)
            ->first();

        return $latest?->store_id ?? (in_array(Store::defaultId(), $stores, true) ? Store::defaultId() : $stores[0]);
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
