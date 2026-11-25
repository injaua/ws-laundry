<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\User;
use App\Services\ProductionPlanner;

class ProductionStatusTool extends AiTool
{
    public function name(): string
    {
        return "production_status";
    }

    public function description(): string
    {
        return "Production now: orders per stage, late orders, due today and tomorrow, ready and not collected, and the daily capacity when it is set.";
    }

    public function ability(): ?string
    {
        return "work-counter";
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        $late = Order::late()->with("customer:id,name")->orderBy("delivery_date")->limit(self::LIMIT)->get();

        return [
            "by_stage" => Order::inProduction()->selectRaw("status, COUNT(*) as n")->groupBy("status")->pluck("n", "status")->all(),
            "late_count" => Order::late()->count(),
            "late" => $late->map(fn (Order $o) => [
                "number" => $o->order_number,
                "customer" => $o->customer?->name,
                "promised" => self::date($o->delivery_date),
                "status" => $o->status,
                "link" => route("orders.show", $o),
            ])->all(),
            "due_today" => Order::dueToday()->count(),
            "due_tomorrow" => Order::inProduction()->whereDate("delivery_date", today()->addDay())->count(),
            "ready_not_collected" => Order::unclaimed()->count(),
            "capacity" => ProductionPlanner::enabled() ? ProductionPlanner::capacity() : null,
            "link" => route("production.board"),
        ];
    }
}
