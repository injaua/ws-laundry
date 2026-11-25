<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\StoreOverview;
use App\Support\CurrentStore;

class SalesSummaryTool extends AiTool
{
    public function name(): string
    {
        return "sales_summary";
    }

    public function description(): string
    {
        return "Business figures of a period: orders, sales, money received, refunds, expenses, result (received - refunds - expenses), "
            . "receivables, late orders, customers. scope=company (admin only) gives every store and the company totals.";
    }

    public function ability(): ?string
    {
        return "view-reports";
    }

    protected function properties(): array
    {
        return [
            ...self::periodProperties(),
            "scope" => ["type" => "string", "enum" => ["store", "company"], "description" => "store (default) or company (admin only)"],
        ];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        [$from, $to] = self::period($input);
        $overview = app(StoreOverview::class);
        $format = fn (array $row) => collect($row)->map(fn ($v, $k) => in_array($k, ["sales", "received", "refunds", "expenses", "result", "receivables"], true) ? money($v) : $v)->all();
        $period = ["from" => $from->toDateString(), "to" => $to->toDateString()];

        if (($input["scope"] ?? "store") === "company") {
            if (!$user->isAdmin()) {
                return ["error" => "Only the administrator sees the company figures."];
            }
            $data = $overview->build($from, $to);

            return [
                "period" => $period,
                "stores" => $data["stores"]->map(fn ($row) => ["store" => $row["store"]->name] + $format(collect($row)->except("store")->all()))->all(),
                "totals" => $format($data["totals"]),
            ];
        }

        $store = CurrentStore::store();
        if (!$store) {
            return ["error" => "No store selected."];
        }

        return ["period" => $period, "store" => $store->name] + $format($overview->figures($store, $from, $to));
    }
}
