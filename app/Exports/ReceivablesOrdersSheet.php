<?php

namespace App\Exports;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReceivablesOrdersSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected Collection $orders) {}

    public function title(): string
    {
        return __("app.open_credit_orders");
    }

    public function headings(): array
    {
        return [
            __("app.order"),
            __("app.customer"),
            __("app.received"),
            __("app.completed"),
            __("app.due_date"),
            __("app.total_price"),
            __("app.paid"),
            __("app.outstanding_balance"),
            __("app.days_overdue"),
            __("app.status"),
        ];
    }

    public function array(): array
    {
        return $this->orders->map(fn (Order $order) => [
            $order->order_number,
            $order->customer?->name ?? "-",
            $order->received_at?->format(date_fmt()),
            $order->completed_at?->format(date_fmt()),
            $order->due_date?->format(date_fmt()),
            Money::round($order->total_price),
            Money::round($order->paidAmount()),
            Money::round($order->remainingBalance()),
            $order->daysOverdue(),
            $order->status_label,
        ])->all();
    }
}
