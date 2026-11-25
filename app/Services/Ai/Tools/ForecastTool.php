<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Forecast;

class ForecastTool extends AiTool
{
    public function name(): string
    {
        return "forecast";
    }

    public function description(): string
    {
        return "Yesterday's figures and the forecast (orders, sales, money received) for tomorrow, the next 7 days and the month "
            . "(month to date, projected month end, last month). scope=company (admin only) gives every store and the totals.";
    }

    public function ability(): ?string
    {
        return "view-reports";
    }

    protected function properties(): array
    {
        return ["scope" => ["type" => "string", "enum" => ["store", "company"]]];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        $forecast = app(Forecast::class);
        if (($input["scope"] ?? "store") === "company") {
            return $user->isAdmin() ? $forecast->company() : ["error" => "Only the administrator sees the company figures."];
        }

        return $forecast->store();
    }
}
