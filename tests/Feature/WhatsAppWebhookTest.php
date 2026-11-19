<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = "test-app-secret";

    protected function setUp(): void
    {
        parent::setUp();
        config(["whatsapp.cloud.app_secret" => self::SECRET, "whatsapp.cloud.verify_token" => "verify-me"]);
    }

    public function test_subscription_handshake(): void
    {
        $this->get("/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345")
            ->assertOk()->assertSee("12345");

        $this->get("/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345")
            ->assertForbidden();
    }

    public function test_unsigned_or_badly_signed_calls_are_rejected(): void
    {
        $payload = $this->inboundPayload("258841234567", "olá");

        $this->postJson("/webhooks/whatsapp", $payload)->assertStatus(401);
        $this->postJson("/webhooks/whatsapp", $payload, ["X-Hub-Signature-256" => "sha256=deadbeef"])->assertStatus(401);

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_unsigned_calls_are_rejected_outside_local_when_no_secret_is_set(): void
    {
        config(["whatsapp.cloud.app_secret" => null]);
        $this->app["env"] = "production";

        $this->postJson("/webhooks/whatsapp", $this->inboundPayload("258841234567", "olá"))->assertStatus(401);
        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_inbound_message_is_stored_and_linked_to_customer(): void
    {
        $customer = Customer::create(["name" => "Maria", "phone" => "84 123 4567", "address" => "Matola"]);

        $this->signedPost($this->inboundPayload("258841234567", "A minha roupa já está pronta?"))->assertOk();

        $message = WhatsAppMessage::firstOrFail();
        $this->assertSame("in", $message->direction);
        $this->assertSame($customer->id, $message->customer_id);
        $this->assertSame("A minha roupa já está pronta?", $message->body);
        $this->assertSame("Maria WA", $message->meta["profile_name"]);
        $this->assertNull($message->read_by_staff_at);
    }

    public function test_duplicate_deliveries_are_stored_once(): void
    {
        $payload = $this->inboundPayload("258841234567", "olá", "wamid.SAME");

        $this->signedPost($payload)->assertOk();
        $this->signedPost($payload)->assertOk();

        $this->assertSame(1, WhatsAppMessage::count());
    }

    public function test_unknown_number_creates_a_conversation_without_customer(): void
    {
        $this->signedPost($this->inboundPayload("258870000000", "Quanto custa lavar um edredão?"))->assertOk();

        $this->assertNull(WhatsAppMessage::firstOrFail()->customer_id);
    }

    public function test_stop_keyword_opts_the_customer_out(): void
    {
        $customer = Customer::create(["name" => "Maria", "phone" => "841234567", "address" => "Matola"]);

        $this->signedPost($this->inboundPayload("258841234567", " parar "))->assertOk();

        $this->assertTrue($customer->fresh()->whatsapp_opt_out);
    }

    public function test_status_updates_move_forward_only(): void
    {
        $out = WhatsAppMessage::create([
            "phone" => "258841234567", "direction" => "out", "type" => "text", "body" => "oi",
            "status" => "sent", "provider_message_id" => "wamid.OUT1",
        ]);

        $this->signedPost($this->statusPayload("wamid.OUT1", "read"))->assertOk();
        $this->assertSame("read", $out->fresh()->status);

        // A late "delivered" must not downgrade "read".
        $this->signedPost($this->statusPayload("wamid.OUT1", "delivered"))->assertOk();
        $this->assertSame("read", $out->fresh()->status);
    }

    public function test_failed_status_records_the_error(): void
    {
        $out = WhatsAppMessage::create([
            "phone" => "258841234567", "direction" => "out", "type" => "text", "body" => "oi",
            "status" => "sent", "provider_message_id" => "wamid.OUT2",
        ]);

        $this->signedPost($this->statusPayload("wamid.OUT2", "failed", [["code" => 131047, "title" => "Re-engagement message"]]))->assertOk();

        $this->assertSame("failed", $out->fresh()->status);
        $this->assertSame("131047 Re-engagement message", $out->fresh()->error);
    }

    private function signedPost(array $payload)
    {
        $body = json_encode($payload);

        return $this->call("POST", "/webhooks/whatsapp", [], [], [], [
            "CONTENT_TYPE" => "application/json",
            "HTTP_X_HUB_SIGNATURE_256" => "sha256=" . hash_hmac("sha256", $body, self::SECRET),
        ], $body);
    }

    private function inboundPayload(string $from, string $text, ?string $id = null): array
    {
        return ["object" => "whatsapp_business_account", "entry" => [["changes" => [["field" => "messages", "value" => [
            "messaging_product" => "whatsapp",
            "contacts" => [["wa_id" => $from, "profile" => ["name" => "Maria WA"]]],
            "messages" => [["from" => $from, "id" => $id ?? "wamid." . uniqid(), "timestamp" => (string) time(), "type" => "text", "text" => ["body" => $text]]],
        ]]]]]];
    }

    private function statusPayload(string $id, string $status, array $errors = []): array
    {
        return ["object" => "whatsapp_business_account", "entry" => [["changes" => [["field" => "messages", "value" => [
            "statuses" => [array_filter(["id" => $id, "status" => $status, "timestamp" => (string) time(), "errors" => $errors ?: null])],
        ]]]]]];
    }
}
