<?php

namespace App\Services\Ai;

use App\Models\AiMessage;
use App\Models\AiRun;
use App\Models\User;

/** Records what every AI call cost and enforces the monthly budget and the daily question limit. */
class UsageMeter
{
    /** Spent this month (USD) from a configuration level. */
    public function spentThisMonth(?int $level): float
    {
        return (float) AiRun::atLevel($level)->thisMonth()->sum("cost");
    }

    /** @return array{runs: int, input_tokens: int, output_tokens: int, cost: float} */
    public function monthUsage(?int $level): array
    {
        $row = AiRun::atLevel($level)->thisMonth()
            ->selectRaw("COUNT(*) as runs, COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens, COALESCE(SUM(cost), 0) as cost")
            ->first();

        return [
            "runs" => (int) $row?->runs,
            "input_tokens" => (int) $row?->input_tokens,
            "output_tokens" => (int) $row?->output_tokens,
            "cost" => round((float) $row?->cost, 4),
        ];
    }

    public function questionsToday(User $user): int
    {
        return AiMessage::where("role", "user")
            ->whereHas("conversation", fn ($q) => $q->where("user_id", $user->id))
            ->where("created_at", ">=", today())
            ->count();
    }

    /** Throws when the configuration cannot be used or a limit is reached. */
    public function guard(AiConfig $config, ?User $asker = null): void
    {
        if (!$config->usable()) {
            throw new AiException(__("app.ai_not_configured"));
        }
        if ($config->monthlyBudget > 0 && $this->spentThisMonth($config->level) >= $config->monthlyBudget) {
            throw new AiException(__("app.ai_budget_reached"));
        }
        if ($asker && $config->dailyQuestions > 0 && $this->questionsToday($asker) >= $config->dailyQuestions) {
            throw new AiException(__("app.ai_daily_limit", ["limit" => $config->dailyQuestions]));
        }
    }

    public function record(AiConfig $config, string $purpose, ?int $userId, ?AiResult $result, ?string $error = null): void
    {
        AiRun::create([
            "store_id" => $config->level,
            "user_id" => $userId,
            "purpose" => $purpose,
            "provider" => $config->provider,
            "model" => mb_substr($config->model, 0, 100),
            "input_tokens" => $result?->inputTokens ?? 0,
            "output_tokens" => $result?->outputTokens ?? 0,
            "cost" => $result ? $config->cost($result->inputTokens, $result->outputTokens) : 0,
            "succeeded" => $error === null,
            "error" => $error === null ? null : mb_substr($error, 0, 500),
        ]);
    }
}
