<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\AccountsReceivable;

class ReceivablesTool extends AiTool
{
    public function name(): string
    {
        return "receivables";
    }

    public function description(): string
    {
        return "Accounts receivable of credit customers: total, overdue, due next week, aging buckets, and the customers who owe most "
            . "(with credit limit and whether they are over it).";
    }

    public function ability(): ?string
    {
        return "manage-finance";
    }

    protected function properties(): array
    {
        return self::limitProperty();
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        $service = app(AccountsReceivable::class);
        $orders = $service->outstandingOrders();
        $summary = $service->summary($orders);

        return [
            "orders_open" => $summary["count"],
            "total" => money($summary["total"]),
            "overdue" => money($summary["overdue"]),
            "overdue_orders" => $summary["overdue_count"],
            "due_next_7_days" => money($summary["due_next_week"]),
            "aging" => collect($summary["buckets"])->map(fn ($v) => money($v))->all(),
            "customers" => $service->agingByCustomer($orders)->take(self::limit($input))->map(fn ($row) => [
                "customer" => $row["customer"]?->name,
                "owes" => money($row["total"]),
                "overdue" => money($row["overdue"]),
                "credit_limit" => money($row["credit_limit"]),
                "over_limit" => $row["credit_limit"] > 0 && $row["total"] > $row["credit_limit"],
                "aging" => collect($row["buckets"])->filter(fn ($v) => $v > 0)->map(fn ($v) => money($v))->all(),
            ])->all(),
            "link" => route("receivables.index"),
        ];
    }
}
