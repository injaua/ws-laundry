<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Statement of account for a credit customer: every movement on their credit orders
 * in date order with a running balance. The closing balance equals the sum of the
 * orders' remainingBalance(): sale (+), credit note (−), refund (+), payment (−).
 */
class CustomerStatement
{
    /**
     * @return array{opening: float, closing: float, debits: float, credits: float, lines: Collection}
     */
    public function build(Customer $customer, Carbon $from, Carbon $to): array
    {
        $movements = $this->movements($customer);

        $opening = round((float) $movements->filter(fn ($m) => $m["date"]->lt($from))->sum("signed"), 2);
        $balance = $opening;

        $lines = $movements
            ->filter(fn ($m) => $m["date"]->betweenIncluded($from, $to))
            ->values()
            ->map(function ($m) use (&$balance) {
                $balance = round($balance + $m["signed"], 2);

                return $m + ["balance" => $balance];
            });

        return [
            "opening" => $opening,
            "closing" => $balance,
            "debits" => round((float) $lines->sum("debit"), 2),
            "credits" => round((float) $lines->sum("credit"), 2),
            "lines" => $lines,
        ];
    }

    private function movements(Customer $customer): Collection
    {
        $orders = $customer->orders()
            ->where("is_credit", true)
            ->where("status", "completed")
            ->with(["payments", "creditNotes", "invoice"])
            ->get();

        $rows = collect();

        foreach ($orders as $order) {
            $date = ($order->completed_at ?? $order->received_at)->copy()->startOfDay();
            $rows->push($this->row($date, "sale", $order->order_number, $order->invoice?->number, (float) $order->total_price, 0, $order->id));

            foreach ($order->creditNotes->filter(fn (CreditNote $note) => $note->voided_at === null) as $note) {
                $noteDate = ($note->issued_at ?? $note->created_at)->copy()->startOfDay();
                $rows->push($this->row($noteDate, "credit_note", $note->number ?? $order->order_number, $order->invoice?->number, 0, (float) $note->amount, $order->id));

                if ($note->settlement === "refund") {
                    $rows->push($this->row($noteDate, "refund", $note->number ?? $order->order_number, $order->invoice?->number, (float) $note->amount, 0, $order->id));
                }
            }

            foreach ($order->payments as $payment) {
                /** @var Payment $payment */
                $rows->push($this->row($payment->paid_at->copy()->startOfDay(), "payment", $payment->group_ref ?? $order->order_number, $order->invoice?->number, 0, (float) $payment->amount, $order->id, $payment->method));
            }
        }

        // Same day: charges before settlements, so the running balance never dips below zero.
        $order = ["sale" => 0, "refund" => 1, "credit_note" => 2, "payment" => 3];

        return $rows->sort(fn ($a, $b) => [$a["date"], $order[$a["type"]], $a["order_id"]] <=> [$b["date"], $order[$b["type"]], $b["order_id"]])->values();
    }

    private function row(Carbon $date, string $type, string $reference, ?string $invoice, float $debit, float $credit, int $orderId, ?string $method = null): array
    {
        return [
            "date" => $date,
            "type" => $type,
            "reference" => $reference,
            "invoice" => $invoice,
            "method" => $method,
            "debit" => round($debit, 2),
            "credit" => round($credit, 2),
            "signed" => round($debit - $credit, 2),
            "order_id" => $orderId,
        ];
    }
}
