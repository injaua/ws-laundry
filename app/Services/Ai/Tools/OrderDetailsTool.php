<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Models\User;

class OrderDetailsTool extends AiTool
{
    public function name(): string
    {
        return "order_details";
    }

    public function description(): string
    {
        return "Full details of one order by its number: customer, items, status and stage history, promised date, payments, credit notes, balance and complaints.";
    }

    public function ability(): ?string
    {
        return "view-records";
    }

    protected function properties(): array
    {
        return ["order_number" => ["type" => "string", "description" => "The order number"]];
    }

    protected function required(): array
    {
        return ["order_number"];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        $number = self::text($input, "order_number", 40);
        $order = Order::with(["customer", "items.service", "items.package", "payments", "creditNotes", "complaints"])
            ->where("order_number", $number)->first();
        if (!$order) {
            return ["error" => "Order {$number} not found in this store."];
        }

        $summary = $order->paymentSummary();

        return [
            "number" => $order->order_number,
            "link" => route("orders.show", $order),
            "customer" => ["name" => $order->customer?->name, "phone" => $context->phone($order->customer?->phone), "type" => $order->customer?->type],
            "status" => $order->status,
            "received" => self::date($order->received_at),
            "promised" => self::date($order->delivery_date),
            "original_promise" => self::date($order->original_delivery_date),
            "delay_reason" => $order->delay_reason,
            "ready_at" => $order->ready_at?->format("Y-m-d H:i"),
            "late" => $order->isLate(),
            "express" => (bool) $order->is_express,
            "items" => $order->items->map(fn ($i) => [
                "service" => $i->service?->name ?? $i->package?->name,
                "type" => $i->type,
                "quantity" => $i->amount,
                "weight_kg" => $i->weight,
                "total" => money($i->subtotal),
            ])->all(),
            "total" => money($order->total_price),
            "paid" => money($summary["paid"]),
            "credited" => money($summary["credited"]),
            "balance" => money(max(0, $order->remainingBalance())),
            "credit_sale" => (bool) $order->is_credit,
            "due_date" => self::date($order->due_date),
            "payments" => $order->payments->map(fn ($p) => ["date" => self::date($p->paid_at), "amount" => money($p->amount), "method" => $p->method])->all(),
            "complaints" => $order->complaints->map(fn ($c) => ["number" => $c->number, "category" => $c->category, "status" => $c->status])->all(),
        ];
    }
}
