<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Support\Money;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    public const TYPES = ["individual", "business"];

    /** per_order: each credit order is its own invoice; monthly: orders are grouped into a consolidated invoice. */
    public const BILLING_CYCLES = ["per_order", "monthly"];

    protected $fillable = [
        'name', 'type', 'legal_name', 'nuit', 'phone', 'email', 'contact_person', 'whatsapp_opt_out',
        'address', 'billing_address', 'is_credit_customer', 'credit_limit', 'credit_terms_days',
        'billing_cycle', 'discount_percent', 'delivery_zone_id', 'address_reference',
    ];

    protected $appends = ['points', 'total_transactions'];

    protected function casts(): array
    {
        return [
            'is_credit_customer' => 'boolean',
            'whatsapp_opt_out' => 'boolean',
            'credit_limit' => 'decimal:2',
            'credit_terms_days' => 'integer',
            'discount_percent' => 'decimal:2',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function latestOrder()
    {
        return $this->hasOne(Order::class)->latestOfMany();
    }

    public function messageLogs()
    {
        return $this->hasMany(CustomerMessageLog::class);
    }

    public function deliveryZone()
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function courierJobs()
    {
        return $this->hasMany(CourierJob::class);
    }

    /** Matches a phone typed in any format (+258 84…, 84…, spaces) on its last 9 digits. */
    public static function findByPhone(string $phone): ?self
    {
        $digits = preg_replace("/\D+/", "", $phone);
        if (strlen($digits) < 9) {
            return null;
        }

        $last9 = substr($digits, -9);

        return static::where("phone", "like", "%" . substr($last9, -4))->get()
            ->first(fn (self $customer) => substr(preg_replace("/\D+/", "", (string) $customer->phone), -9) === $last9);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function prices()
    {
        return $this->hasMany(CustomerPrice::class);
    }

    public function isBusiness(): bool
    {
        return $this->type === "business";
    }

    public function billsMonthly(): bool
    {
        return $this->is_credit_customer && $this->billing_cycle === "monthly";
    }

    /** Name printed on fiscal documents: the registered company name when there is one. */
    public function fiscalName(): string
    {
        return $this->isBusiness() && filled($this->legal_name) ? $this->legal_name : $this->name;
    }

    public function fiscalAddress(): ?string
    {
        return filled($this->billing_address) ? $this->billing_address : $this->address;
    }

    /**
     * Unit price this customer pays: a negotiated price wins, otherwise the catalog
     * price less the customer's general discount.
     */
    public function priceFor(Service|Package $item): float
    {
        $column = $item instanceof Service ? "service_id" : "package_id";
        $negotiated = $this->relationLoaded("prices")
            ? $this->prices->firstWhere($column, $item->id)
            : $this->prices()->where($column, $item->id)->first();

        if ($negotiated) {
            return Money::round($negotiated->price);
        }

        $discount = (float) $this->discount_percent;

        return $discount > 0
            ? Money::discount($item->price, $discount)
            : Money::round($item->price);
    }

    /**
     * Pricing data for the order form: only customers with special conditions.
     *
     * @return array<int, array{discount: float, services: array<int, float>, packages: array<int, float>}>
     */
    public static function pricingMap(): array
    {
        return static::with("prices")
            ->where(fn ($q) => $q->where("discount_percent", ">", 0)->orWhereHas("prices"))
            ->get()
            ->mapWithKeys(fn (self $c) => [$c->id => [
                "discount" => (float) $c->discount_percent,
                "services" => $c->prices->whereNotNull("service_id")->mapWithKeys(fn ($p) => [$p->service_id => (float) $p->price])->all(),
                "packages" => $c->prices->whereNotNull("package_id")->mapWithKeys(fn ($p) => [$p->package_id => (float) $p->price])->all(),
            ]])
            ->all();
    }

    /**
     * Digits-only number for wa.me links; Mozambican local numbers get the 258 prefix.
     */
    public function normalizedWhatsapp(): ?string
    {
        $digits = preg_replace("/\D+/", "", (string) $this->phone);

        if ($digits === "" || $digits === null) {
            return null;
        }

        if (strlen($digits) === 9) {
            $digits = SystemSettings::phoneCountryDigits() . $digits;
        }

        return $digits;
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    /**
     * Find the customer behind a WhatsApp number (digits with country code), whatever
     * format the phone was typed in. Matches on the last 9 digits, then confirms.
     */
    public static function findByWhatsapp(string $phone): ?self
    {
        $tail = substr($phone, -9);

        if (strlen($tail) < 9) {
            return null;
        }

        $matches = fn (self $c) => $c->normalizedWhatsapp() === $phone;

        // Fast path: the last 4 digits are almost always typed together.
        $found = static::where("phone", "like", "%" . substr($tail, -4))->get()->first($matches);

        // Phones typed with separators inside the last digits ("84 123 45 67").
        return $found ?? static::whereNotNull("phone")->where("phone", "!=", "")->lazy()->first($matches);
    }

    public function loyaltyPoints()
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    /**
     * Earn loyalty points when order is completed.
     * 1 point per 1,000 Mts (or 10 points per 10,000 Mts).
     */
    public function earnPoints(Order $order): void
    {
        $pointsEarned = floor($order->total_price / 1000);

        if ($pointsEarned > 0) {
            LoyaltyPoint::create([
                "customer_id" => $this->id,
                "points" => $pointsEarned,
                "type" => "earned",
                "note" => "Order #" . $order->order_number,
                "source_type" => Order::class,
                "source_id" => $order->id,
            ]);

            $this->increment("points", $pointsEarned);
        }
    }

    /**
     * Redeem loyalty points for discount.
     * 100 points = 5,000 Mts discount.
     */
    public function redeemPoints(int $points, Order $order): int
    {
        if ($this->points < $points) {
            throw new \Exception(__('app.insufficient_points', ['available' => $this->points]));
        }

        $discount = floor($points / 100) * 5000;

        if ($discount > $order->total_price) {
            $discount = $order->total_price;
            $points = ceil($discount / 5000) * 100;
        }

        LoyaltyPoint::create([
            "customer_id" => $this->id,
            "points" => -$points,
            "type" => "redeemed",
            "note" => "Points redeemed for order #" . $order->order_number . " (discount " . money($discount) . ")",
            "source_type" => Order::class,
            "source_id" => $order->id,
        ]);

        $this->decrement("points", $points);
        $order->decrement("total_price", $discount);

        return $discount;
    }

    public function getPointsAttribute(): int
    {
        return $this->attributes["points"] ?? 0;
    }

    public function getTotalTransactionsAttribute(): int
    {
        return $this->orders()->count();
    }

    /**
     * Unpaid balance of the customer's delivered credit orders.
     */
    public function creditOutstandingBalance(): float
    {
        return Money::sum($this->orders()->outstandingCredit()->get()
            ->map(fn (Order $order) => $order->remainingBalance()));
    }

    public function overdueCreditOrders()
    {
        return $this->orders()->overdueCredit();
    }

    public function hasOverdueCredit(): bool
    {
        return $this->overdueCreditOrders()->exists();
    }

    /**
     * Second factor for the public order-status lookup: the given digits must be
     * exactly the last 4 digits of the customer's phone.
     */
    public function phoneEndsWith(string $last4): bool
    {
        $digits = preg_replace('/\D/', "", (string) $this->phone);

        return strlen($last4) === 4 && strlen($digits) >= 4 && str_ends_with($digits, $last4);
    }
}
