<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\SiteSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Unit prices for order lines and the express surcharge.
 *
 * Price of a line = the lowest of
 *   - the customer's price (negotiated price, or catalog less their general discount)
 *   - the catalog price less the best promotion running on the order date
 * Discounts never stack.
 */
class Pricing
{
    /** @var array<string, Collection<int, Promotion>> */
    private array $running = [];

    /**
     * @return array{unit_price: float, list_price: float, promotion_id: ?int}
     */
    public function quote(Customer $customer, Service|Package $item, CarbonInterface $date): array
    {
        $list = (float) $item->price;
        $customerPrice = $customer->priceFor($item);
        [$promoPrice, $promotion] = $this->bestPromotion($item, $list, $date);

        if ($promotion && $promoPrice < $customerPrice) {
            return ["unit_price" => $promoPrice, "list_price" => $list, "promotion_id" => $promotion->id];
        }

        return ["unit_price" => $customerPrice, "list_price" => $list, "promotion_id" => null];
    }

    /**
     * @return array{0: float, 1: ?Promotion}
     */
    public function bestPromotion(Service|Package $item, float $base, CarbonInterface $date): array
    {
        $best = [$base, null];

        foreach ($this->runningOn($date) as $promotion) {
            if (!$promotion->appliesTo($item)) {
                continue;
            }

            $price = $promotion->discountedPrice($base);
            if ($price < $best[0]) {
                $best = [$price, $promotion];
            }
        }

        return $best;
    }

    /** @return Collection<int, Promotion> */
    public function runningOn(CarbonInterface $date): Collection
    {
        return $this->running[$date->toDateString()] ??= Promotion::with("items")
            ->where("is_active", true)
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->isRunningOn($date))
            ->values();
    }

    /** Promotions the order form may need (active and not finished). */
    public static function promotionsForOrderForm(): array
    {
        return Promotion::with("items")
            ->where("is_active", true)
            ->where(fn ($q) => $q->whereNull("ends_on")->orWhereDate("ends_on", ">=", today()->subDays(31)))
            ->get()
            ->map(fn (Promotion $promotion) => $promotion->toPricingArray())
            ->values()
            ->all();
    }

    // ---- Express (urgent) service ----

    /**
     * @return array{enabled: bool, type: string, value: float, days: int}
     */
    public static function expressSettings(): array
    {
        return [
            "enabled" => (bool) SiteSetting::get("express_enabled", false),
            "type" => SiteSetting::get("express_surcharge_type", "percent") === "fixed" ? "fixed" : "percent",
            "value" => (float) SiteSetting::get("express_surcharge_value", 50),
            "days" => max(0, (int) SiteSetting::get("express_lead_days", 1)),
        ];
    }

    /** Surcharge on the lines' subtotal (before VAT): a % of it, or a fixed amount per order. */
    public function expressFee(float $itemsSubtotal): float
    {
        $settings = self::expressSettings();

        if (!$settings["enabled"] || $itemsSubtotal <= 0) {
            return 0.0;
        }

        return $settings["type"] === "fixed"
            ? round($settings["value"])
            : round($itemsSubtotal * $settings["value"] / 100);
    }
}
