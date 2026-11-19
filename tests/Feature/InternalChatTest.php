<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_can_open_chat(): void
    {
        foreach ([User::factory()->create(), User::factory()->owner()->create(), User::factory()->cashier()->create()] as $user) {
            $this->actingAs($user)->get("/messages")->assertOk();
        }
    }

    public function test_direct_conversation_is_created_once_per_pair(): void
    {
        $admin = User::factory()->create();
        $cashier = User::factory()->cashier()->create();

        foreach (["a", "b"] as $round) {
            $this->actingAs($admin)->post("/messages/conversations", [
                "type" => "direct",
                "user_ids" => [$cashier->id],
            ])->assertRedirect();
        }

        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, Conversation::first()->participants()->count());
    }

    public function test_cannot_chat_with_yourself(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/messages/conversations", [
            "type" => "direct",
            "user_ids" => [$admin->id],
        ])->assertSessionHasErrors("user_ids");

        $this->assertSame(0, Conversation::count());
    }

    public function test_meeting_requires_title_and_two_others(): void
    {
        $admin = User::factory()->create();
        $b = User::factory()->cashier()->create();

        // only one other user -> rejected
        $this->actingAs($admin)->post("/messages/conversations", [
            "type" => "meeting", "title" => "Plan", "user_ids" => [$b->id],
        ])->assertSessionHasErrors("user_ids");

        // no title -> rejected
        $c = User::factory()->owner()->create();
        $this->actingAs($admin)->post("/messages/conversations", [
            "type" => "meeting", "user_ids" => [$b->id, $c->id],
        ])->assertSessionHasErrors("title");

        $this->actingAs($admin)->post("/messages/conversations", [
            "type" => "meeting", "title" => "Planeamento", "user_ids" => [$b->id, $c->id],
        ])->assertSessionHasNoErrors();

        $meeting = Conversation::firstWhere("type", "meeting");
        $this->assertNotNull($meeting);
        $this->assertSame(3, $meeting->participants()->count());
    }

    public function test_non_participant_cannot_read_or_post(): void
    {
        $admin = User::factory()->create();
        $cashier = User::factory()->cashier()->create();
        $outsider = User::factory()->owner()->create();

        $this->actingAs($admin)->post("/messages/conversations", ["type" => "direct", "user_ids" => [$cashier->id]]);
        $conversation = Conversation::first();

        $this->actingAs($outsider)->get("/messages/conversations/{$conversation->id}/poll")->assertForbidden();
        $this->actingAs($outsider)->post("/messages/conversations/{$conversation->id}/messages", ["body" => "oi"])->assertForbidden();
        $this->actingAs($outsider)->get("/messages/conversations/{$conversation->id}")->assertForbidden();
    }

    public function test_messages_appear_in_poll_and_index(): void
    {
        $admin = User::factory()->create();
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($admin)->post("/messages/conversations", ["type" => "direct", "user_ids" => [$cashier->id]]);
        $conversation = Conversation::first();

        $this->actingAs($admin)->post("/messages/conversations/{$conversation->id}/messages", ["body" => "Bom dia!"])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/messages/conversations/{$conversation->id}/messages", ["body" => ""])
            ->assertSessionHasErrors("body");

        $this->actingAs($cashier)->get("/messages/conversations/{$conversation->id}/poll")
            ->assertOk()
            ->assertJsonPath("messages.0.body", "Bom dia!")
            ->assertJsonPath("messages.0.mine", false);

        $this->actingAs($cashier)->get("/messages?conversation={$conversation->id}")->assertOk()->assertSee("Bom dia!");
    }

    public function test_unread_count_tracks_other_users_messages(): void
    {
        $admin = User::factory()->create();
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($admin)->post("/messages/conversations", ["type" => "direct", "user_ids" => [$cashier->id]]);
        $conversation = Conversation::first();
        $this->actingAs($admin)->post("/messages/conversations/{$conversation->id}/messages", ["body" => "Olá"])->assertSessionHasNoErrors();

        $this->actingAs($cashier)->get("/messages/unread-count")->assertJsonPath("count", 1);
        $this->actingAs($admin)->get("/messages/unread-count")->assertJsonPath("count", 0);

        // opening the thread marks it read
        $this->actingAs($cashier)->get("/messages")->assertOk();
        $this->actingAs($cashier)->get("/messages/unread-count")->assertJsonPath("count", 0);
    }

    public function test_poll_returns_only_new_messages_with_since(): void
    {
        $admin = User::factory()->create();
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($admin)->post("/messages/conversations", ["type" => "direct", "user_ids" => [$cashier->id]]);
        $conversation = Conversation::first();
        $this->actingAs($admin)->post("/messages/conversations/{$conversation->id}/messages", ["body" => "primeira"]);

        $first = $this->actingAs($cashier)->getJson("/messages/conversations/{$conversation->id}/poll")->json();
        $lastId = $first["messages"][0]["id"];

        $this->actingAs($admin)->post("/messages/conversations/{$conversation->id}/messages", ["body" => "segunda"]);

        $second = $this->actingAs($cashier)->getJson("/messages/conversations/{$conversation->id}/poll?since={$lastId}")->json();
        $this->assertCount(1, $second["messages"]);
        $this->assertSame("segunda", $second["messages"][0]["body"]);
    }
}
