<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\User;

class SearchOrdersTool extends AiTool
{
    public function name(): string
    {
        return "search_orders";
    }

    public function description(): string
    {
        return "Search the store's orders by order number or customer name, status, received period, "
            . "or only the late ones / due today / ready and not collected. Returns number, customer, status, dates, total and balance.";
    }

    public function ability(): ?string
    {
        return "view-records";
    }

    protected function properties(): array
    {
        return [
            "query" => ["type" => "string", "description" => "Order number or part of the customer's name"],
            "status" => ["type" => "string", "enum" => Order::STATUSES],
            "filter" => ["type" => "string", "enum" => ["late", "due_today", "due_tomorrow", "unclaimed", "unpaid"], "description" => "Special list"],
            ...self::periodProperties(),
            ...self::limitProperty(),
        ];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        $query = Order::query()->with(["customer:id,name", "payments", "creditNotes"]);

        $term = self::text($input, "query");
        if ($term !== "") {
            $like = "%" . addcslashes($term, "%_\\") . "%";
            $query->where(fn ($q) => $q->where("order_number", "like", $like)
                ->orWhereHas("customer", fn ($c) => $c->where("name", "like", $like)));
        }
        if (in_array($input["status"] ?? null, Order::STATUSES, true)) {
            $query->where("status", $input["status"]);
        }
        match ($input["filter"] ?? null) {
            "late" => $query->late(),
            "due_today" => $query->dueToday(),
            "due_tomorrow" => $query->inProduction()->whereDate("delivery_date", today()->addDay()),
            "unclaimed" => $query->unclaimed(),
            "unpaid" => $query->outstanding(),
            default => null,
        };
        if (isset($input["from"]) || isset($input["to"])) {
            [$from, $to] = self::period($input);
            $query->whereBetween("received_at", [$from->toDateString(), $to->toDateString()]);
        }

        $total = (clone $query)->count();
        $orders = $query->latest("received_at")->latest("id")->limit(self::limit($input))->get();

        return [
            "total_found" => $total,
            "orders" => $orders->map(fn (Order $o) => [
                "number" => $o->order_number,
                "customer" => $o->customer?->name,
                "status" => $o->status,
                "received" => self::date($o->received_at),
                "promised" => self::date($o->delivery_date),
                "late" => $o->isLate(),
                "express" => (bool) $o->is_express,
                "total" => money($o->total_price),
                "balance" => money(max(0, $o->remainingBalance())),
                "credit_sale" => (bool) $o->is_credit,
                "link" => route("orders.show", $o),
            ])->all(),
        ];
    }
}
