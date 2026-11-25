<?php

namespace App\Services\Ai\Tools;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;

class SearchCustomersTool extends AiTool
{
    public function name(): string
    {
        return "search_customers";
    }

    public function description(): string
    {
        return "Find customers by name or phone, or list the best customers / those who stopped coming. "
            . "Returns type, credit terms, number of orders, total spent, last order and amount owed.";
    }

    public function ability(): ?string
    {
        return "view-records";
    }

    protected function properties(): array
    {
        return [
            "query" => ["type" => "string", "description" => "Part of the name or phone"],
            "list" => ["type" => "string", "enum" => ["top_spenders", "inactive_regulars", "credit_customers"], "description" => "Ranked list instead of a search"],
            ...self::limitProperty(),
        ];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        $query = Customer::query()
            ->withCount("orders")
            ->withSum("orders as spent", "total_price")
            ->withMax("orders as last_order", "received_at");

        $term = self::text($input, "query");
        if ($term !== "") {
            $like = "%" . addcslashes($term, "%_\\") . "%";
            $query->where(fn ($q) => $q->where("name", "like", $like)->orWhere("phone", "like", $like));
        }

        match ($input["list"] ?? null) {
            "top_spenders" => $query->orderByDesc("spent"),
            // Came at least 3 times in the 6 months before, but not in the last 45 days.
            "inactive_regulars" => $query
                ->whereHas("orders", fn ($o) => $o->whereBetween("received_at", [today()->subMonths(6), today()->subDays(45)]), ">=", 3)
                ->whereDoesntHave("orders", fn ($o) => $o->where("received_at", ">", today()->subDays(45)))
                ->orderByDesc("spent"),
            "credit_customers" => $query->where("is_credit_customer", true)->orderBy("name"),
            default => $query->orderBy("name"),
        };

        $customers = $query->limit(self::limit($input))->get();
        $owed = Order::outstanding()->whereIn("customer_id", $customers->pluck("id"))->with(["payments", "creditNotes"])->get()
            ->groupBy("customer_id")->map(fn ($orders) => $orders->sum(fn (Order $o) => max(0, $o->remainingBalance())));

        return [
            "customers" => $customers->map(fn (Customer $c) => [
                "name" => $c->name,
                "type" => $c->type,
                "phone" => $context->phone($c->phone),
                "credit_customer" => (bool) $c->is_credit_customer,
                "credit_limit" => $c->is_credit_customer ? money($c->credit_limit) : null,
                "orders" => (int) $c->orders_count,
                "spent" => money((float) $c->spent),
                "last_order" => $c->last_order ? substr((string) $c->last_order, 0, 10) : null,
                "owes" => money((float) ($owed[$c->id] ?? 0)),
                "link" => route("customers.show", $c),
            ])->all(),
        ];
    }
}
