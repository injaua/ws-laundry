<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Services\Ai\AiException;
use App\Services\Ai\AiSettings;
use App\Services\Ai\AssistantService;
use App\Services\Ai\UsageMeter;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The AI assistant: the user's conversations and the questions (answered over AJAX). */
class AssistantController extends Controller
{
    public function index(Request $request, UsageMeter $meter): View
    {
        $user = $request->user();
        $conversations = AiConversation::ownedBy($user)->latest("updated_at")->limit(50)->get(["id", "title", "updated_at"]);

        $current = null;
        if ($request->filled("c")) {
            $current = AiConversation::ownedBy($user)->with("messages")->find((int) $request->query("c"));
        }
        $config = AiSettings::forStore(CurrentStore::id());

        return view("assistant.index", [
            "conversations" => $conversations,
            "current" => $current,
            "ready" => $config->usable(),
            "missing" => $config->missing(),
            "canConfigure" => $user->can("configure-ai"),
            "questionsLeft" => $config->dailyQuestions > 0 ? max(0, $config->dailyQuestions - $meter->questionsToday($user)) : null,
            "suggestions" => $this->suggestions($request),
            "prefill" => mb_substr((string) $request->query("q", ""), 0, 500),
        ]);
    }

    public function ask(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            "question" => ["required", "string", "max:2000"],
            "conversation" => ["nullable", "integer"],
        ]);
        $user = $request->user();
        $conversation = null;
        if (!empty($data["conversation"])) {
            $conversation = AiConversation::ownedBy($user)->findOrFail($data["conversation"]);
        }

        // Several tools and model steps can take a while.
        @set_time_limit(300);

        try {
            ["conversation" => $conversation, "answer" => $answer] = $assistant->ask($user, $conversation, trim($data["question"]));
        } catch (AiException $e) {
            return response()->json(["error" => $e->getMessage()], 422);
        }

        return response()->json([
            "conversation" => $conversation->id,
            "title" => $conversation->title,
            "url" => route("assistant.index", ["c" => $conversation->id]),
            "html" => $answer->html(),
            "failed" => $answer->failed,
        ]);
    }

    public function destroy(Request $request, AiConversation $conversation): RedirectResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        $conversation->delete();

        return redirect()->route("assistant.index")->with("success", __("app.ai_conversation_deleted"));
    }

    /** @return string[] example questions for the user's role */
    private function suggestions(Request $request): array
    {
        $user = $request->user();
        $keys = match (true) {
            $user->isManager() => ["ai_q_yesterday", "ai_q_forecast_month", "ai_q_late", "ai_q_receivables", "ai_q_stock"],
            $user->isAccountant() => ["ai_q_expenses", "ai_q_receivables", "ai_q_trial_vat", "ai_q_yesterday"],
            $user->isCourier() => ["ai_q_route", "ai_q_help"],
            default => ["ai_q_due_today", "ai_q_late", "ai_q_till", "ai_q_help"],
        };

        return array_map(fn ($k) => __("app." . $k), $keys);
    }
}
