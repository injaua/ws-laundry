<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class OrderExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $from;
    protected $to;

    public function __construct($from, $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function query()
    {
        return Order::query()
            ->with(["customer", "items.service", "items.package", "payments"])
            ->whereBetween("received_at", [$this->from, $this->to])
            ->orderBy("received_at");
    }

    public function headings(): array
    {
        return ["Order Number", "Customer", "Received Date", "Status", "Total Price", "Paid"];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            $order->customer->name ?? "-",
            $order->received_at->format("d/m/Y"),
            ucfirst($order->status),
            (float) $order->total_price,
            (float) $order->payments->sum("amount"),
        ];
    }
}
