<?php

namespace App\Services\Ai\Tools;

use App\Models\AiToolCall;
use App\Models\User;
use Throwable;

/**
 * The tools of the assistant. The model is only told about the tools the user's role allows;
 * a call to any other tool (or one the role lost meanwhile) is refused and recorded.
 */
class ToolRegistry
{
    public const TOOLS = [
        SearchOrdersTool::class,
        OrderDetailsTool::class,
        SearchCustomersTool::class,
        SalesSummaryTool::class,
        ManagementIndicatorsTool::class,
        ForecastTool::class,
        ProductionStatusTool::class,
        ComplaintsTool::class,
        TillStatusTool::class,
        ExpensesTool::class,
        ReceivablesTool::class,
        TrialBalanceTool::class,
        StockTool::class,
        AttendanceTool::class,
        DocumentsTool::class,
        CourierRouteTool::class,
        HelpTool::class,
    ];

    /** Largest tool result given to the model (characters). */
    private const MAX_RESULT = 30000;

    /** @return array<string, AiTool> */
    public function all(): array
    {
        $tools = [];
        foreach (self::TOOLS as $class) {
            $tool = app($class);
            $tools[$tool->name()] = $tool;
        }

        return $tools;
    }

    /** @return array<string, AiTool> */
    public function forUser(User $user): array
    {
        return array_filter($this->all(), fn (AiTool $tool) => $tool->allows($user));
    }

    /** @return list<array{name: string, description: string, parameters: array<string, mixed>}> */
    public function specsFor(User $user): array
    {
        return array_values(array_map(fn (AiTool $tool) => $tool->spec(), $this->forUser($user)));
    }

    /**
     * Runs a tool for the user and records it. Always returns JSON for the model: the result,
     * or an error it can explain to the user.
     *
     * @param  array{conversation_id?: int|null, store_id?: int|null}  $audit
     */
    public function run(User $user, string $name, array $input, ToolContext $context, array $audit = []): string
    {
        $tool = $this->all()[$name] ?? null;
        $started = microtime(true);
        $status = "ok";

        if (!$tool || !$tool->allows($user)) {
            $status = "denied";
            $result = ["error" => "This tool does not exist or the user's role may not use it. Tell the user this information is not available to their profile."];
        } else {
            try {
                $result = $tool->handle($user, $input, $context);
            } catch (Throwable $e) {
                report($e);
                $status = "error";
                $result = ["error" => "The tool failed. Tell the user the data could not be read now."];
            }
        }

        AiToolCall::create([
            "conversation_id" => $audit["conversation_id"] ?? null,
            "user_id" => $user->id,
            "store_id" => $audit["store_id"] ?? null,
            "tool" => mb_substr($name, 0, 60),
            "input" => $input,
            "status" => $status,
            "duration_ms" => (int) round((microtime(true) - $started) * 1000),
        ]);

        $json = (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return mb_strlen($json) > self::MAX_RESULT ? mb_substr($json, 0, self::MAX_RESULT) . "…(truncated)" : $json;
    }
}
