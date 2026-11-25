<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiInsight;
use App\Models\AiRun;
use App\Models\AiToolCall;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\SiteSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiResult;
use App\Services\Ai\AiSettings;
use App\Services\Ai\Insights\InsightGenerator;
use App\Services\Ai\Tools\ToolRegistry;
use App\Support\CurrentStore;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentStore::flush();
    }

    protected function tearDown(): void
    {
        AiManager::fake(null);
        parent::tearDown();
    }

    /** Company AI configuration, switched on. */
    private function configureCompany(array $extra = []): void
    {
        AiSettings::save($extra + [
            "enabled" => true,
            "provider" => "anthropic",
            "model" => "claude-opus-5-5",
            "api_key" => "sk-test-secret",
            "mask_personal" => true,
            "daily_questions" => 30,
        ], null);
    }

    private function ask(User $user, string $question, ?int $conversation = null)
    {
        return $this->actingAs($user)->postJson(route("assistant.ask"), ["question" => $question, "conversation" => $conversation]);
    }

    /** @return string[] */
    private function toolNames(User $user): array
    {
        return array_column(app(ToolRegistry::class)->specsFor($user), "name");
    }

    public function test_every_role_opens_the_assistant_and_only_managers_the_insights(): void
    {
        foreach ([User::factory()->create(), User::factory()->owner()->create(), User::factory()->cashier()->create(), User::factory()->accountant()->create(), User::factory()->courier()->create()] as $user) {
            $this->actingAs($user)->get(route("assistant.index"))->assertOk()->assertSee(__("app.ai_welcome_title"));
        }
        foreach ([User::factory()->create(), User::factory()->owner()->create()] as $user) {
            $this->actingAs($user)->get(route("insights.index"))->assertOk();
        }
        foreach ([User::factory()->cashier()->create(), User::factory()->accountant()->create(), User::factory()->courier()->create()] as $user) {
            $this->actingAs($user)->get(route("insights.index"))->assertForbidden();
            $this->actingAs($user)->post(route("insights.toggle"))->assertForbidden();
            $this->actingAs($user)->get(route("admin.ai.edit"))->assertForbidden();
        }
    }

    public function test_the_model_only_sees_the_tools_of_the_role(): void
    {
        $cashier = $this->toolNames(User::factory()->cashier()->create());
        $this->assertContains("search_orders", $cashier);
        $this->assertContains("till_status", $cashier);
        $this->assertNotContains("expenses", $cashier);
        $this->assertNotContains("sales_summary", $cashier);
        $this->assertNotContains("receivables", $cashier);

        $accountant = $this->toolNames(User::factory()->accountant()->create());
        $this->assertContains("trial_balance", $accountant);
        $this->assertContains("documents_search", $accountant);
        $this->assertNotContains("stock_status", $accountant);
        $this->assertNotContains("hr_attendance", $accountant);

        $courier = $this->toolNames(User::factory()->courier()->create());
        $this->assertEqualsCanonicalizing(["courier_route", "help"], $courier);

        $this->assertContains("hr_attendance", $this->toolNames(User::factory()->owner()->create()));
    }

    public function test_every_tool_runs_for_the_roles_that_have_it(): void
    {
        $this->configureCompany();
        $inputs = [
            "order_details" => ["order_number" => "X-1"],
            "sales_summary" => ["scope" => "company"],
            "forecast" => ["scope" => "company"],
            "help" => ["topic" => ""],
        ];
        foreach ([User::factory()->create(), User::factory()->owner()->create(), User::factory()->cashier()->create(), User::factory()->accountant()->create(), User::factory()->courier()->create()] as $user) {
            AiManager::fake(function ($system, $history, $tools, $runTool) use ($inputs) {
                foreach (array_column($tools, "name") as $name) {
                    $runTool($name, $inputs[$name] ?? []);
                }

                return "ok";
            });
            $this->ask($user, "Tudo")->assertOk();
        }

        $this->assertSame(0, AiToolCall::where("status", "!=", "ok")->count(), AiToolCall::where("status", "!=", "ok")->pluck("tool")->implode(", "));
        $this->assertSame(count(ToolRegistry::TOOLS), AiToolCall::distinct()->count("tool"));
    }

    public function test_a_question_is_answered_and_a_forbidden_tool_is_refused(): void
    {
        $this->configureCompany();
        $cashier = User::factory()->cashier()->create();
        $results = [];

        AiManager::fake(function ($system, $history, $tools, $runTool) use (&$results) {
            $results["expenses"] = json_decode($runTool("expenses", []), true);
            $results["production"] = json_decode($runTool("production_status", []), true);

            return "**Há 0 pedidos atrasados.**";
        });

        $response = $this->ask($cashier, "Que pedidos estão atrasados?")->assertOk()->json();

        $this->assertStringContainsString("<strong>Há 0 pedidos atrasados.</strong>", $response["html"]);
        $this->assertArrayHasKey("error", $results["expenses"]);
        $this->assertSame(0, $results["production"]["late_count"]);
        $this->assertSame("denied", AiToolCall::where("tool", "expenses")->value("status"));
        $this->assertSame("ok", AiToolCall::where("tool", "production_status")->value("status"));

        $conversation = AiConversation::findOrFail($response["conversation"]);
        $this->assertSame($cashier->id, $conversation->user_id);
        $this->assertSame(["user", "assistant"], $conversation->messages->pluck("role")->all());
        $this->assertSame(1, AiRun::where("purpose", "chat")->where("succeeded", true)->count());

        // The conversation is private to its user.
        $this->ask(User::factory()->cashier()->create(), "E agora?", $conversation->id)->assertNotFound();
    }

    public function test_the_earlier_turns_go_with_a_follow_up_question(): void
    {
        $this->configureCompany();
        $owner = User::factory()->owner()->create();
        $seen = [];
        AiManager::fake(function ($system, $history) use (&$seen) {
            $seen = $history;

            return "Resposta " . count($history);
        });

        $id = $this->ask($owner, "Primeira")->json("conversation");
        $this->ask($owner, "Segunda", $id)->assertOk();

        $this->assertSame(["user", "assistant", "user"], array_column($seen, "role"));
        $this->assertSame("Segunda", end($seen)["text"]);
    }

    public function test_personal_data_is_masked_for_the_ai(): void
    {
        $this->configureCompany();
        $owner = User::factory()->owner()->create();
        CurrentStore::run(Store::defaultId(), fn () => Customer::create(["name" => "Maria Teste", "phone" => "+258841234567"]));
        $found = null;
        AiManager::fake(function ($system, $history, $tools, $runTool) use (&$found) {
            $found = json_decode($runTool("search_customers", ["query" => "Maria"]), true);

            return "ok";
        });

        $this->ask($owner, "Telefone da Maria?")->assertOk();

        $this->assertSame("Maria Teste", $found["customers"][0]["name"]);
        $this->assertSame("•••4567", $found["customers"][0]["phone"]);
    }

    public function test_without_configuration_or_over_the_daily_limit_nothing_is_asked(): void
    {
        $owner = User::factory()->owner()->create();
        AiManager::fake(fn () => "nunca");

        $this->ask($owner, "Olá")->assertStatus(422)->assertJson(["error" => __("app.ai_not_configured")]);

        $this->configureCompany(["daily_questions" => 1]);
        $this->ask($owner, "Olá")->assertOk();
        $this->ask($owner, "Outra")->assertStatus(422)->assertJson(["error" => __("app.ai_daily_limit", ["limit" => 1])]);
    }

    public function test_the_key_is_encrypted_and_never_sent_back(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route("admin.ai.update"), [
            "level" => "company",
            "enabled" => "1",
            "provider" => "openai",
            "model" => "gpt-test",
            "api_key" => "sk-very-secret",
            "base_url" => "https://api.openai.com/v1",
            "daily_questions" => 10,
            "mask_personal" => "1",
        ])->assertRedirect()->assertSessionHas("success");

        $config = AiSettings::at(null);
        $this->assertTrue($config->usable());
        $this->assertSame("sk-very-secret", $config->apiKey);
        $stored = SiteSetting::whereNull("store_id")->where("key", "ai_api_key")->value("value");
        $this->assertNotSame("sk-very-secret", $stored);
        $this->assertSame("sk-very-secret", Crypt::decryptString($stored));

        $this->actingAs($admin)->get(route("admin.ai.edit", ["level" => "company"]))->assertOk()->assertDontSee("sk-very-secret");

        // A blank key keeps the stored one.
        $this->actingAs($admin)->put(route("admin.ai.update"), ["level" => "company", "enabled" => "1", "provider" => "openai", "model" => "gpt-other", "api_key" => ""]);
        $this->assertSame("sk-very-secret", AiSettings::at(null)->apiKey);
        $this->assertSame("gpt-other", AiSettings::at(null)->model);
    }

    public function test_the_page_says_what_is_missing_when_the_ai_cannot_answer(): void
    {
        AiSettings::save(["enabled" => true, "provider" => "anthropic", "model" => "claude-opus-5-5"], null);

        $this->actingAs(User::factory()->cashier()->create())->get(route("assistant.index"))->assertOk()
            ->assertSee(__("app.ai_missing_api_key"))
            ->assertSee(__("app.ai_ask_manager"))
            ->assertSee('data-ready="0"', false)
            ->assertSee('id="aiWelcomeTemplate"', false);
    }

    public function test_the_models_of_the_provider_are_listed(): void
    {
        $admin = User::factory()->create();
        Http::fake([
            "api.openai.com/v1/models" => Http::response(["data" => [["id" => "gpt-b"], ["id" => "gpt-a"], ["id" => "text-embedding-3"]]]),
            "generativelanguage.googleapis.com/*" => Http::response(["models" => [
                ["name" => "models/gemini-x", "displayName" => "Gemini X", "supportedGenerationMethods" => ["generateContent"]],
                ["name" => "models/embed-y", "supportedGenerationMethods" => ["embedContent"]],
            ]]),
        ]);

        $this->actingAs($admin)->postJson(route("admin.ai.models"), ["provider" => "openai", "api_key" => "sk-x", "level" => "company"])
            ->assertOk()->assertExactJson(["models" => [["id" => "gpt-a", "name" => "gpt-a"], ["id" => "gpt-b", "name" => "gpt-b"]]]);
        Http::assertSent(fn ($request) => $request->hasHeader("Authorization", "Bearer sk-x"));

        $this->actingAs($admin)->postJson(route("admin.ai.models"), ["provider" => "gemini", "api_key" => "g-key", "level" => "company"])
            ->assertOk()->assertExactJson(["models" => [["id" => "gemini-x", "name" => "Gemini X"]]]);

        // Without a key (and none saved for that provider): the provider is not asked.
        $this->actingAs($admin)->postJson(route("admin.ai.models"), ["provider" => "gemini", "level" => "company"])
            ->assertStatus(422)->assertJson(["error" => __("app.ai_models_need_key")]);

        $this->actingAs(User::factory()->cashier()->create())->postJson(route("admin.ai.models"), ["provider" => "openai"])->assertForbidden();
        $this->actingAs($admin)->get(route("admin.ai.edit"))->assertOk()->assertSee("claude-sonnet-5-5")->assertSee('id="ai_model_select"', false);
    }

    public function test_a_model_that_only_answers_on_the_responses_api_is_called_there(): void
    {
        $config = new \App\Services\Ai\AiConfig(null, true, "openai", "openai", "chatgpt-6-luna", "sk-x", "https://api.openai.com/v1",
            null, null, 0, 30, true, true);
        Http::fake([
            "api.openai.com/v1/chat/completions" => Http::response(["error" => ["message" => "This model is only supported in v1/responses and not in v1/chat/completions."]], 404),
            "api.openai.com/v1/responses" => Http::sequence()
                ->push(["output" => [
                    ["type" => "reasoning", "id" => "rs_1", "summary" => []],
                    ["type" => "function_call", "call_id" => "call_1", "name" => "sales", "arguments" => '{"days":7}'],
                ], "usage" => ["input_tokens" => 10, "output_tokens" => 5]])
                ->push(["output" => [
                    ["type" => "message", "content" => [["type" => "output_text", "text" => "Vendeu 100."]]],
                ], "usage" => ["input_tokens" => 20, "output_tokens" => 3]]),
        ]);

        $driver = new \App\Services\Ai\Drivers\OpenAiCompatibleDriver($config);
        $tools = [["name" => "sales", "description" => "Sales", "parameters" => ["type" => "object", "properties" => new \stdClass()]]];
        $seen = [];
        $result = $driver->run("sys", [["role" => "user", "text" => "Vendas?"]], $tools, function ($name, $args) use (&$seen) {
            $seen[] = [$name, $args];

            return '{"total":100}';
        });

        $this->assertSame("Vendeu 100.", $result->text);
        $this->assertSame([["sales", ["days" => 7]]], $seen);
        $this->assertSame(30, $result->inputTokens);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), "/responses") && $r["instructions"] === "sys"
            && collect($r["input"])->contains(fn ($i) => ($i["type"] ?? "") === "function_call_output" && $i["call_id"] === "call_1"));

        // Remembered: the next run goes straight to /responses.
        Http::fake(["api.openai.com/v1/responses" => Http::response(["output" => [["type" => "message", "content" => [["type" => "output_text", "text" => "Ok"]]]]])]);
        $this->assertSame("Ok", $driver->run("sys", [["role" => "user", "text" => "Oi"]], [], fn () => "")->text);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), "chat/completions"));
    }

    public function test_the_owner_configures_only_their_own_store(): void
    {
        $this->configureCompany();
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route("admin.ai.edit"))->assertOk()->assertDontSee("sk-test-secret");
        $this->actingAs($owner)->put(route("admin.ai.update"), [
            "level" => "company", // ignored: the owner always saves their store
            "enabled" => "1",
            "provider" => "gemini",
            "model" => "gemini-test",
            "api_key" => "store-key",
        ])->assertRedirect();

        $this->assertSame("store-key", AiSettings::forStore(Store::defaultId())->apiKey);
        $this->assertSame("gemini", AiSettings::forStore(Store::defaultId())->provider);
        // The company configuration is untouched, and was not copied into the store.
        $this->assertSame("sk-test-secret", AiSettings::at(null)->apiKey);
        $this->assertSame("anthropic", AiSettings::at(null)->provider);
    }

    public function test_the_insights_switch(): void
    {
        $owner = User::factory()->owner()->create();
        $this->assertTrue($owner->fresh()->ai_insights_enabled);

        $this->actingAs($owner)->post(route("insights.toggle"))->assertRedirect();
        $this->assertFalse($owner->fresh()->ai_insights_enabled);
        $this->actingAs($owner)->get(route("insights.index"))->assertOk()->assertSee(__("app.ai_insights_turn_on"));

        $this->actingAs($owner)->post(route("insights.toggle"));
        $this->assertTrue($owner->fresh()->ai_insights_enabled);
    }

    public function test_insights_are_made_without_ai_and_only_for_those_who_receive_them(): void
    {
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->create();
        CurrentStore::run(Store::defaultId(), fn () => InventoryItem::create([
            "kind" => "product", "name" => "Detergente", "unit" => "l", "stock_qty" => 1, "min_stock_qty" => 5, "unit_cost" => 100, "is_active" => true,
        ]));
        $today = CarbonImmutable::parse("2026-11-25");

        $result = app(InsightGenerator::class)->run($today);
        $this->assertGreaterThanOrEqual(3, $result["insights"]);

        $store = AiInsight::where("audience", "store")->where("store_id", Store::defaultId());
        $this->assertSame(1, (clone $store)->where("kind", "briefing")->count());
        $alert = (clone $store)->where("detector", "low_stock")->firstOrFail();
        $this->assertFalse($alert->ai_written);
        $this->assertStringContainsString("Detergente", $alert->body);
        $company = AiInsight::where("audience", "company")->whereNull("store_id")->firstOrFail();
        $this->assertSame("briefing", $company->kind);

        // The same day again: nothing new.
        $this->assertSame(0, app(InsightGenerator::class)->run($today)["insights"]);

        // The owner sees their store's insights, not the company's; the admin both.
        $this->actingAs($owner)->get(route("insights.index"))->assertOk()->assertSee("Detergente")->assertDontSee($company->title);
        $this->actingAs($admin)->get(route("insights.index"))->assertOk()->assertSee($company->title);

        // Nobody receiving: nothing is made.
        User::query()->update(["ai_insights_enabled" => false]);
        $this->assertSame(0, app(InsightGenerator::class)->run($today->addDay())["insights"]);
    }

    public function test_the_ai_writes_the_insights_when_configured(): void
    {
        $this->configureCompany();
        User::factory()->owner()->create();
        CurrentStore::run(Store::defaultId(), fn () => InventoryItem::create([
            "kind" => "product", "name" => "Amaciador", "unit" => "l", "stock_qty" => 0, "min_stock_qty" => 2, "unit_cost" => 50, "is_active" => true,
        ]));
        AiManager::fake(fn () => new AiResult(json_encode([
            "briefing" => ["title" => "Dia calmo", "body" => "Ontem sem pedidos.", "recommendation" => "Prepare a semana."],
            "alerts" => ["low_stock" => ["title" => "Amaciador esgotado", "body" => "Sem amaciador.", "recommendation" => "Encomende hoje."]],
        ]), 500, 200));

        app(InsightGenerator::class)->run(CarbonImmutable::parse("2026-11-25"));

        $alert = AiInsight::where("detector", "low_stock")->firstOrFail();
        $this->assertTrue($alert->ai_written);
        $this->assertSame("Amaciador esgotado", $alert->title);
        $this->assertSame("critical", $alert->severity);
        $this->assertSame("Dia calmo", AiInsight::where("kind", "briefing")->where("audience", "store")->value("title"));
        $this->assertGreaterThan(0, (float) AiRun::sum("cost"));
    }

    public function test_an_owner_cannot_act_on_another_stores_insight(): void
    {
        $other = Store::create(["name" => "Loja 2", "code" => "LJ02", "is_active" => true]);
        $insight = AiInsight::create([
            "store_id" => $other->id, "audience" => "store", "kind" => "alert", "severity" => "info", "title" => "Outra loja",
            "body" => "x", "data" => [], "fingerprint" => "t", "period_date" => today(), "status" => "new", "ai_written" => false,
        ]);
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route("insights.status", $insight), ["status" => "resolved"])->assertNotFound();
        $this->actingAs($owner)->get(route("insights.index"))->assertDontSee("Outra loja");
    }
}
