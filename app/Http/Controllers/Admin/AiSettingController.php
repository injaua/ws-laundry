<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ChoosesSettingsLevel;
use App\Http\Controllers\Controller;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiSettings;
use App\Services\Ai\ModelCatalog;
use App\Services\Ai\UsageMeter;
use App\Support\CurrentStore;
use App\Support\StoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * AI provider of the assistant and the insights: any provider (Anthropic, Gemini or any
 * OpenAI-compatible service), its model, key, prices, budget and limits. For the company, or
 * for a store (the admin chooses; the owner configures their own store).
 */
class AiSettingController extends Controller
{
    use ChoosesSettingsLevel;

    public function edit(Request $request, UsageMeter $meter): View
    {
        $level = $this->settingsLevel($request, "ai");
        $config = AiSettings::at($level);

        return view("admin.ai.edit", [
            "config" => $config,
            "hasKey" => AiSettings::hasKey($level),
            "providers" => AiSettings::PROVIDERS,
            "suggestedModels" => ModelCatalog::suggestions(),
            "usage" => $meter->monthUsage($level),
            "levelBar" => $this->levelBar("ai", $level, "admin.ai.edit"),
            "levelParam" => $level === null ? "company" : "store",
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "level" => ["nullable", Rule::in(["company", "store"])],
            "enabled" => ["nullable", "boolean"],
            "provider" => ["required", Rule::in(array_keys(AiSettings::PROVIDERS))],
            "model" => ["nullable", "string", "max:100", "regex:/^[\w.:\/@-]+$/"],
            "api_key" => ["nullable", "string", "max:500"],
            "forget_key" => ["nullable", "boolean"],
            "base_url" => ["nullable", "url:http,https", "max:255"],
            "price_input" => ["nullable", "numeric", "min:0", "max:10000"],
            "price_output" => ["nullable", "numeric", "min:0", "max:10000"],
            "monthly_budget" => ["nullable", "numeric", "min:0", "max:1000000"],
            "daily_questions" => ["nullable", "integer", "min:0", "max:10000"],
            "mask_personal" => ["nullable", "boolean"],
        ]);
        if ($data["provider"] === "custom" && empty($data["base_url"])) {
            return back()->withInput()->withErrors(["base_url" => __("app.ai_base_url_required")]);
        }
        $level = $this->settingsLevel($request, "ai", forSaving: true);

        AiSettings::save($data, $level);

        $back = redirect()->route("admin.ai.edit", $this->levelQuery($level, "ai"));
        $config = AiSettings::at($level);
        if ($config->enabled && !$config->complete()) {
            return $back->with("warning", __("app.ai_settings_incomplete"));
        }

        return $back->with("success", __("app.ai_settings_saved"));
    }

    /**
     * The models the chosen provider offers for the key typed in (or the one saved, when the
     * provider is the saved one). The key is only used for this request.
     */
    public function models(Request $request, ModelCatalog $catalog): JsonResponse
    {
        $data = $request->validate([
            "level" => ["nullable", Rule::in(["company", "store"])],
            "provider" => ["required", Rule::in(array_keys(AiSettings::PROVIDERS))],
            "base_url" => ["nullable", "url:http,https", "max:255"],
            "api_key" => ["nullable", "string", "max:500"],
        ]);
        $saved = AiSettings::at($this->settingsLevel($request, "ai"));
        $key = trim((string) ($data["api_key"] ?? ""));
        if ($key === "" && $saved->provider === $data["provider"]) {
            $key = $saved->apiKey;
        }

        try {
            $models = $catalog->live($data["provider"], $key, (string) ($data["base_url"] ?? ""));
        } catch (AiException $e) {
            return response()->json(["error" => $e->getMessage()], 422);
        }

        return response()->json(["models" => $models]);
    }

    /** A short call with the saved configuration, to check the key and the model. */
    public function test(Request $request, UsageMeter $meter): RedirectResponse
    {
        $level = $this->settingsLevel($request, "ai");
        $config = AiSettings::at($level);
        $back = redirect()->route("admin.ai.edit", $this->levelQuery($level, "ai"));
        if (!$config->complete()) {
            return $back->with("warning", __("app.ai_settings_incomplete"));
        }

        try {
            $result = AiManager::driver($config)->run("Reply with the single word OK.", [["role" => "user", "text" => "Test"]], [], fn () => "{}", maxSteps: 1, maxTokens: 2000);
        } catch (AiException $e) {
            $meter->record($config, "test", $request->user()->id, null, $e->getMessage());

            return $back->with("error", $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $meter->record($config, "test", $request->user()->id, null, $e->getMessage());

            return $back->with("error", __("app.ai_error_provider", ["message" => mb_substr($e->getMessage(), 0, 200)]));
        }
        $meter->record($config, "test", $request->user()->id, $result);

        return $back->with("success", __("app.ai_test_ok", ["model" => $config->model, "answer" => mb_substr($result->text, 0, 60)]));
    }

    /** The store worked in goes back to the company AI configuration. */
    public function release(): RedirectResponse
    {
        $storeId = CurrentStore::id();
        abort_unless($storeId && StoreSettings::owns("ai", $storeId), 404);

        StoreSettings::release("ai", $storeId);

        return redirect()->route("admin.ai.edit", ["level" => "store"])->with("success", __("app.store_settings_released"));
    }
}
