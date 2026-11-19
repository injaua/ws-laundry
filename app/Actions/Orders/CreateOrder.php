<?php

namespace App\Actions\Orders;

use App\Models\CourierJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\PieceTracker;
use App\Services\Pricing;
use App\Services\ProductionPlanner;
use App\Services\WhatsApp\AutomaticNotifications;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an order: prices every line from the catalog (never from the browser),
 * applies IVA, fills the delivery date, enforces the customer's credit policy and
 * persists the order with a unique sequential number.
 */
class CreateOrder
{
    public function __construct(
        private AutomaticNotifications $notifications,
        private PieceTracker $pieces,
        private Pricing $pricing,
    ) {}

    private const NUMBER_ATTEMPTS = 5;

    /**
     * @param  array  $data  validated order fields
     * @param  array  $items  raw item rows (service_id|package_id, weight, amount)
     *
     * @throws ValidationException when the credit policy rejects the order
     */
    public function handle(array $data, array $items, User $user): Order
    {
        $customer = Customer::with("prices")->findOrFail($data["customer_id"]);
        $rows = $this->priceItems($items, $customer, now()->parse($data["received_at"]));

        // Express (urgent) surcharge is service revenue: it is part of the subtotal, before VAT.
        $itemsSubtotal = array_sum(array_column($rows, "subtotal"));
        $isExpress = (bool) ($data["is_express"] ?? false) && Pricing::expressSettings()["enabled"];
        $expressFee = $isExpress ? $this->pricing->expressFee($itemsSubtotal) : 0.0;
        // Clothes collected at home: the pickup trip is billed on this order.
        $pickup = $this->pickupFor($data, $customer);
        $pickupFee = $pickup ? $this->pickupFee($pickup, $itemsSubtotal) : 0.0;
        $subtotal = $itemsSubtotal + $expressFee + $pickupFee;
        $vatRate = (float) SiteSetting::get("vat_rate", 16);
        $vatAmount = round($subtotal * $vatRate / 100);
        $total = $subtotal + $vatAmount;

        $deliveryDate = $data["delivery_date"]
            ?? ($isExpress
                ? now()->parse($data["received_at"])->addDays(Pricing::expressSettings()["days"])->toDateString()
                : $this->plannedDeliveryDate($items, $rows, $data["received_at"]));

        $isCredit = (bool) ($data["is_credit"] ?? false);
        $credit = $isCredit
            ? $this->checkCredit($data, $total, $deliveryDate, $user)
            : ["due_date" => null, "override_note" => null];

        $attributes = [
            "customer_id" => $data["customer_id"],
            "received_at" => $data["received_at"],
            "delivery_date" => $deliveryDate,
            "subtotal" => $subtotal,
            "is_express" => $isExpress,
            "express_fee" => $expressFee,
            "delivery_fee" => $pickupFee,
            "vat_rate" => $vatRate,
            "vat_amount" => $vatAmount,
            "total_price" => $total,
            "notes" => $data["notes"] ?? null,
            "status" => "received",
            "is_credit" => $isCredit,
            "due_date" => $credit["due_date"],
            "credit_override_note" => $credit["override_note"],
            "purchase_order_ref" => $data["purchase_order_ref"] ?? null,
            "care_flags" => array_values(array_intersect($data["care_flags"] ?? [], Order::CARE_FLAGS)) ?: null,
            "intake_notes" => $data["intake_notes"] ?? null,
        ];

        // Two cashiers saving at the same moment can compute the same next number;
        // the unique index rejects the second insert, so retry with a fresh number.
        for ($attempt = 1; ; $attempt++) {
            try {
                $order = DB::transaction(function () use ($attributes, $rows, $user, $pickup, $pickupFee) {
                    $order = $this->persist($attributes, $rows, $user);
                    $pickup?->update(["order_id" => $order->id, "fee" => $pickupFee]);

                    return $order;
                });
                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        $this->notifications->orderReceived($order);

        return $order;
    }

    /** The completed pickup this order is created from, if any; it must belong to the same customer. */
    private function pickupFor(array $data, Customer $customer): ?CourierJob
    {
        if (empty($data["pickup_job_id"])) {
            return null;
        }

        $pickup = CourierJob::with("zone")->find($data["pickup_job_id"]);

        if (!$pickup || !$pickup->awaitsOrder() || (int) $pickup->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages(["pickup_job_id" => __("app.pickup_not_available")]);
        }

        return $pickup;
    }

    /** Pickup trips are free once the clothes reach the zone's free threshold. */
    private function pickupFee(CourierJob $pickup, float $itemsSubtotal): float
    {
        $zone = $pickup->zone;

        return $zone && $zone->free_from !== null && $itemsSubtotal >= (float) $zone->free_from ? 0.0 : (float) $pickup->fee;
    }

    /** Unit prices: the lowest of the customer's price and the catalog price less the running promotion. */
    private function priceItems(array $items, Customer $customer, CarbonInterface $date): array
    {
        $rows = [];

        foreach ($items as $item) {
            $service = !empty($item["service_id"]) ? Service::findOrFail($item["service_id"]) : null;

            if ($service) {
                $quantity = $service->type === "by_weight"
                    ? max(0.5, (float) ($item["weight"] ?? 0))
                    : max(1, (int) ($item["amount"] ?? 1));

                $quote = $this->pricing->quote($customer, $service, $date);
                $rows[] = [
                    "service_id" => $service->id,
                    "package_id" => null,
                    "type" => $service->type,
                    "weight" => $service->type === "by_weight" ? $quantity : null,
                    "amount" => $service->type === "by_piece" ? (int) $quantity : 1,
                    ...$quote,
                    "subtotal" => round($quote["unit_price"] * $quantity),
                ];

                continue;
            }

            $package = Package::findOrFail($item["package_id"]);
            $quantity = max(1, (int) ($item["amount"] ?? 1));

            $quote = $this->pricing->quote($customer, $package, $date);
            $rows[] = [
                "service_id" => null,
                "package_id" => $package->id,
                "type" => "by_package",
                "weight" => round((float) $package->weight_kg * $quantity, 2),
                "amount" => $quantity,
                ...$quote,
                "subtotal" => round($quote["unit_price"] * $quantity),
            ];
        }

        return $rows;
    }

    /**
     * Promise date: the longest service lead time, pushed back when the production
     * queue does not leave room for this order (daily capacity, when configured).
     */
    private function plannedDeliveryDate(array $items, array $rows, string $receivedAt): ?string
    {
        $serviceDate = $this->expectedDeliveryDate($items, $receivedAt);

        if (!ProductionPlanner::enabled()) {
            return $serviceDate;
        }

        $kg = (float) collect($rows)->whereIn("type", ["by_weight", "by_package"])->sum("weight");
        $pieces = (int) collect($rows)->where("type", "by_piece")->sum("amount");
        $leadDays = $serviceDate ? (int) now()->parse($receivedAt)->startOfDay()->diffInDays(now()->parse($serviceDate)) : 0;

        return app(ProductionPlanner::class)->promise(now()->parse($receivedAt), $kg, $pieces, $leadDays)["date"];
    }

    /** Expected completion date: received date plus the longest service lead time. */
    private function expectedDeliveryDate(array $items, string $receivedAt): ?string
    {
        $days = Service::whereIn("id", collect($items)->pluck("service_id")->filter())->max("estimated_days");

        return $days ? now()->parse($receivedAt)->addDays((int) $days)->toDateString() : null;
    }

    /**
     * Credit exposure: the new order plus what the customer already owes must fit
     * inside the limit, and no order may be overdue. Admin/owner can push through
     * with an explicit justification; cashiers cannot.
     *
     * @return array{due_date: string, override_note: ?string}
     */
    private function checkCredit(array $data, float $total, ?string $deliveryDate, User $user): array
    {
        $customer = Customer::findOrFail($data["customer_id"]);

        if (!$customer->is_credit_customer) {
            throw ValidationException::withMessages(["is_credit" => __("app.not_credit_customer")]);
        }

        $dueDate = now()->parse($deliveryDate ?: $data["received_at"])
            ->addDays((int) $customer->credit_terms_days)
            ->toDateString();

        $violations = [];
        $limit = (float) $customer->credit_limit;
        $outstanding = $customer->creditOutstandingBalance();

        if ($limit > 0 && $outstanding + $total > $limit) {
            $violations[] = __("app.credit_limit_exceeded", [
                "exposure" => money($outstanding + $total),
                "limit" => money($limit),
            ]);
        }

        $overdue = $customer->orders()->overdueCredit()->get();
        if ($overdue->isNotEmpty()) {
            $violations[] = __("app.customer_has_overdue", [
                "count" => $overdue->count(),
                "amount" => money($overdue->sum(fn ($o) => $o->remainingBalance())),
            ]);
        }

        // Monthly-billed customers get their due date from the consolidated invoice.
        if ($customer->billsMonthly()) {
            $dueDate = null;
        }

        if (!$violations) {
            return ["due_date" => $dueDate, "override_note" => null];
        }

        if ($user->isCashier()) {
            throw ValidationException::withMessages([
                "is_credit" => implode(" ", $violations) . " " . __("app.credit_override_forbidden_cashier"),
            ]);
        }

        $note = trim((string) ($data["credit_override_note"] ?? ""));
        if ($note === "") {
            throw ValidationException::withMessages([
                "credit_override_note" => __("validation.required", ["attribute" => "credit override note"]),
            ]);
        }

        return ["due_date" => $dueDate, "override_note" => $note];
    }

    private function persist(array $attributes, array $rows, User $user): Order
    {
        $order = Order::create($attributes + ["order_number" => Order::nextOrderNumber()]);

        foreach ($rows as $row) {
            OrderItem::create($row + ["order_id" => $order->id]);
        }

        // Garments counted at the counter get their own tracking tag right away;
        // pieces of weight/package lines are counted and added on the order page.
        $this->pieces->createForOrder($order, $user);

        return $order;
    }
}
