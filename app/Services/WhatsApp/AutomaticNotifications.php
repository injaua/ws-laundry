<?php

namespace App\Services\WhatsApp;

use App\Models\CourierJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\WhatsAppMessage;
use App\Services\MobileMoney\MobileMoneySettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automatic WhatsApp notifications. Each event is switched on in the settings page
 * and tied to a message template; every notification has a unique key so it is
 * never sent twice, and opted-out customers are skipped.
 */
class AutomaticNotifications
{
    /** Event => [default template name, default days (for scheduled events)]. */
    public const EVENTS = [
        "order_received" => ["Encomenda recebida", null],
        "order_ready" => ["Pronto para recolher", null],
        "payment_received" => ["Pagamento recebido", null],
        "credit_due_soon" => ["Lembrete de pagamento", 2],
        "credit_overdue" => ["Fatura vencida", 1],
        "unclaimed" => ["Peças por levantar", 7],
        "pickup_scheduled" => ["Recolha agendada", null],
        "courier_on_the_way" => ["Estafeta a caminho", null],
        "order_delayed" => ["Encomenda atrasada", null],
    ];

    /** Events triggered by the daily scheduler rather than by an action in the app. */
    public const SCHEDULED = ["credit_due_soon", "credit_overdue", "unclaimed"];

    public function __construct(private Messenger $messenger) {}

    public function enabled(string $event): bool
    {
        return (bool) SiteSetting::get("notify_{$event}_enabled", false);
    }

    public function template(string $event): ?MessageTemplate
    {
        $id = SiteSetting::get("notify_{$event}_template_id");

        return $id ? MessageTemplate::where("is_active", true)->find($id) : null;
    }

    public function days(string $event): int
    {
        return max(1, (int) SiteSetting::get("notify_{$event}_days", self::EVENTS[$event][1] ?? 1));
    }

    public function orderReceived(Order $order): void
    {
        $this->notify("order_received", $order, "order_received:{$order->id}");
    }

    public function orderReady(Order $order): void
    {
        $this->notify("order_ready", $order, "order_ready:{$order->id}");
    }

    /** New promise date for a late order (one message per new date). */
    public function orderDelayed(Order $order): void
    {
        $this->notify("order_delayed", $order, "order_delayed:{$order->id}:" . ($order->delivery_date?->toDateString() ?? ""));
    }

    public function paymentReceived(Payment $payment): void
    {
        $payment->loadMissing("order");

        if ($payment->order) {
            $this->notify("payment_received", $payment->order, "payment_received:{$payment->id}", [
                "amount" => $this->money((float) $payment->amount),
            ]);
        }
    }

    /** One message for a payment split over several orders (or a consolidated invoice). */
    public function groupedPaymentReceived(Collection $payments, ?Invoice $invoice = null): void
    {
        $first = $payments->first();

        if (!$first instanceof Payment) {
            return;
        }

        $first->loadMissing("order");
        if ($first->order) {
            $this->notify("payment_received", $first->order, "payment_received:group:{$first->group_ref}", [
                "amount" => $this->money((float) $payments->sum("amount")),
                "order" => $invoice?->number ?? $payments->map(fn (Payment $p) => $p->order?->order_number)->filter()->join(", "),
            ]);
        }
    }

    /**
     * Daily run: credit invoices due soon or overdue, and orders ready but not collected.
     *
     * @return array<string, int> notifications queued per event
     */
    public function runScheduled(): array
    {
        $sent = array_fill_keys(self::SCHEDULED, 0);

        if ($this->enabled("credit_due_soon")) {
            $days = $this->days("credit_due_soon");
            Order::outstandingCredit()->whereNull("invoice_id")->whereDate("due_date", now()->addDays($days)->toDateString())
                ->with("customer")->get()
                ->each(function (Order $o) use (&$sent) {
                    $sent["credit_due_soon"] += (int) $this->notify("credit_due_soon", $o, "credit_due_soon:{$o->id}:{$o->due_date->toDateString()}");
                });
        }

        if ($this->enabled("credit_overdue")) {
            // Repeat every N days while the invoice stays unpaid.
            $every = $this->days("credit_overdue");
            Order::overdueCredit()->whereNull("invoice_id")->with("customer")->get()
                ->filter(fn (Order $o) => $o->daysOverdue() >= 1 && ($o->daysOverdue() - 1) % $every === 0)
                ->each(function (Order $o) use (&$sent) {
                    $sent["credit_overdue"] += (int) $this->notify("credit_overdue", $o, "credit_overdue:{$o->id}:" . now()->toDateString(), [
                        "days" => $o->daysOverdue(),
                    ]);
                });
        }

        // Consolidated invoices: one reminder per invoice instead of one per order.
        if ($this->enabled("credit_due_soon") || $this->enabled("credit_overdue")) {
            $dueSoonDate = now()->addDays($this->days("credit_due_soon"))->toDateString();
            $every = $this->days("credit_overdue");

            Invoice::issued()->with(["customer", "orders"])->get()
                ->filter(fn (Invoice $invoice) => $invoice->balance() > 0)
                ->each(function (Invoice $invoice) use (&$sent, $dueSoonDate, $every) {
                    $order = $invoice->orders->first();
                    $extra = [
                        "order" => $invoice->number,
                        "amount" => $this->money($invoice->balance()),
                        "due_date" => $invoice->due_date?->format(date_fmt()) ?? "",
                    ];

                    if ($this->enabled("credit_due_soon") && $invoice->due_date?->toDateString() === $dueSoonDate) {
                        $sent["credit_due_soon"] += (int) $this->notify("credit_due_soon", $order, "credit_due_soon:invoice:{$invoice->id}:{$dueSoonDate}", $extra);
                    }

                    $days = $invoice->daysOverdue();
                    if ($this->enabled("credit_overdue") && $days >= 1 && ($days - 1) % $every === 0) {
                        $sent["credit_overdue"] += (int) $this->notify("credit_overdue", $order, "credit_overdue:invoice:{$invoice->id}:" . now()->toDateString(), $extra + ["days" => $days]);
                    }
                });
        }

        if ($this->enabled("unclaimed")) {
            // Ready for N days and not collected: one reminder at N days, another at 4×N.
            $days = $this->days("unclaimed");
            foreach ([$days, $days * 4] as $step) {
                Order::where("status", "ready")
                    ->whereNull("abandoned_at")
                    ->whereDate("ready_at", now()->subDays($step)->toDateString())
                    ->with("customer")->get()
                    ->each(function (Order $o) use (&$sent, $step) {
                        $sent["unclaimed"] += (int) $this->notify("unclaimed", $o, "unclaimed:{$o->id}:{$step}", ["days" => $step]);
                    });
            }
        }

        return $sent;
    }

    /** Home pickup confirmed for a date. */
    public function pickupScheduled(CourierJob $job): void
    {
        $this->notifyCourierJob("pickup_scheduled", $job, "pickup_scheduled:{$job->id}:{$job->scheduled_date->toDateString()}");
    }

    /** The courier left for the customer's address (pickup or delivery). */
    public function courierOnTheWay(CourierJob $job): void
    {
        $this->notifyCourierJob("courier_on_the_way", $job, "courier_on_the_way:{$job->id}:{$job->scheduled_date->toDateString()}");
    }

    /** Placeholders for courier messages: a pickup has no order yet, so it cannot use notify(). */
    public function courierJobVariables(CourierJob $job): array
    {
        $job->loadMissing("customer", "order", "courier");

        return [
            "customer" => $job->customer?->name ?? $job->contact_name,
            "order" => $job->order?->order_number ?? "",
            "date" => $job->scheduled_date->format(date_fmt()),
            "window" => __("app.window_" . $job->time_window),
            "address" => $job->address,
            "courier" => $job->courier?->name ?? "",
            "task" => $job->isPickup()
                ? __("app.wa_task_pickup")
                : __("app.wa_task_delivery", ["order" => $job->order?->order_number ?? ""]),
            "amount" => $this->money($job->amountToCollect()),
        ];
    }

    private function notifyCourierJob(string $event, CourierJob $job, string $key): bool
    {
        try {
            $customer = $job->customer;

            if (!$this->enabled($event) || !$customer instanceof Customer || $customer->whatsapp_opt_out
                || $customer->normalizedWhatsapp() === null || !($template = $this->template($event))) {
                return false;
            }

            if (WhatsAppMessage::where("notification_key", $key)->exists()) {
                return false;
            }

            return $this->messenger->sendTemplate($template, $customer, $this->courierJobVariables($job), null, $job->order, $key) !== null;
        } catch (Throwable $e) {
            Log::warning("[whatsapp] notification {$event} for courier job {$job->id} failed: " . $e->getMessage());

            return false;
        }
    }

    /** Placeholder values for an order's customer and invoice. */
    public function variables(Order $order, array $extra = []): array
    {
        return $extra + [
            "customer" => $order->customer?->name ?? "",
            "order" => $order->order_number,
            "amount" => $this->money($order->is_credit ? $order->remainingBalance() : (float) $order->total_price),
            "due_date" => $order->due_date?->format(date_fmt()) ?? "",
            "delivery_date" => $order->delivery_date?->format(date_fmt()) ?? "",
            "days" => "",
            // Payment link (M-Pesa / e-Mola) while something is owed and an operator is enabled.
            "pay_link" => $order->remainingBalance() > 0 && MobileMoneySettings::enabledProviders() !== [] ? $order->payLink() : "",
        ];
    }

    /** Never lets a messaging problem break the order/payment that triggered it. */
    private function notify(string $event, Order $order, string $key, array $extra = []): bool
    {
        try {
            $customer = $order->customer;

            if (!$this->enabled($event) || !$customer instanceof Customer || $customer->whatsapp_opt_out
                || $customer->normalizedWhatsapp() === null || !($template = $this->template($event))) {
                return false;
            }

            if (WhatsAppMessage::where("notification_key", $key)->exists()) {
                return false;
            }

            return $this->messenger->sendTemplate($template, $customer, $this->variables($order, $extra), null, $order, $key) !== null;
        } catch (Throwable $e) {
            Log::warning("[whatsapp] notification {$event} for order {$order->id} failed: " . $e->getMessage());

            return false;
        }
    }

    private function money(float $amount): string
    {
        return num($amount);
    }
}
