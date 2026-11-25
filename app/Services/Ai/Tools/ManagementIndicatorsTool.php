<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\ManagementIndicators;

class ManagementIndicatorsTool extends AiTool
{
    public function name(): string
    {
        return "management_indicators";
    }

    public function description(): string
    {
        return "Management KPIs of a period compared with the previous period of the same length and with the targets: "
            . "revenue, collections, expenses, operating result and margin, average ticket, receivables, DSO, orders, active/new/returning customers, "
            . "express share, kg processed, on-time rate, cycle hours, rework, complaints, headcount, absenteeism, payroll ratio, material cost, stock. "
            . "Optional group filter: finance, sales, operations, people, costs.";
    }

    public function ability(): ?string
    {
        return "view-reports";
    }

    protected function properties(): array
    {
        return [...self::periodProperties(), "group" => ["type" => "string", "enum" => ManagementIndicators::GROUPS]];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        [$from, $to] = self::period($input);
        $data = app(ManagementIndicators::class)->build($from, $to);
        $group = in_array($input["group"] ?? null, ManagementIndicators::GROUPS, true) ? $input["group"] : null;

        $value = fn ($v, string $format) => $v === null ? null : ($format === "money" ? money($v) : (is_float($v) ? round($v, 2) : $v));

        return [
            "period" => ["from" => $from->toDateString(), "to" => $to->toDateString()],
            "indicators" => collect($data["indicators"])
                ->when($group, fn ($c) => $c->where("group", $group))
                ->map(fn ($i) => [
                    "group" => $i["group"],
                    "unit" => $i["format"],
                    "value" => $value($i["value"], $i["format"]),
                    "previous_period" => $value($i["previous"], $i["format"]),
                    "change_percent" => $i["change"] === null ? null : round((float) $i["change"], 1),
                    "target" => $value($i["target"], $i["format"]),
                    "status" => $i["status"],
                    "better_when" => $i["better"],
                ])->all(),
        ];
    }
}
