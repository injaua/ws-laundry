<?php

namespace App\Services\Ai\Insights;

use App\Models\CashSession;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Document;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\MaintenanceJob;
use App\Models\MobileTransaction;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonImmutable;

/**
 * Rules that look at the data of the store worked in and return what deserves attention.
 * They do not need AI: the AI only writes the signals in better words and connects them.
 * Weekly detectors run on Mondays (or when the run is forced).
 */
class Detectors
{
    /** Ready for this many days and not collected = worth an alert. */
    private const UNCLAIMED_DAYS = 7;

    /** @return list<Signal> */
    public function run(CarbonImmutable $today, bool $weekly): array
    {
        $signals = array_filter([
            $this->lateOrders($today),
            $this->tillShortages($today),
            $this->lowStock($today),
            $this->expiringLots($today),
            $this->overdueReceivables($today),
            $this->unclaimedOrders($today),
            $this->failedMobilePayments($today),
            $this->overdueMaintenance($today),
            $this->complaintSpike($today),
            $weekly ? $this->salesTrend($today) : null,
            $weekly ? $this->lostCustomers($today) : null,
            $weekly ? $this->expiringDocuments($today) : null,
        ]);

        return array_values($signals);
    }

    private function lateOrders(CarbonImmutable $today): ?Signal
    {
        $count = Order::late()->count();
        if ($count === 0) {
            return null;
        }
        $oldest = Order::late()->min("delivery_date");

        return new Signal(
            "late_orders",
            $count >= 5 ? "critical" : "warning",
            __("app.ai_d_late_orders_title", ["count" => $count]),
            __("app.ai_d_late_orders_body", ["count" => $count, "date" => $oldest ? substr((string) $oldest, 0, 10) : "-"]),
            __("app.ai_d_late_orders_tip"),
            ["count" => $count, "oldest_promise" => $oldest],
            route("production.board"),
            "late_orders:" . $today->toDateString(),
        );
    }

    private function tillShortages(CarbonImmutable $today): ?Signal
    {
        $sessions = CashSession::where("status", CashSession::STATUS_CLOSED)->with(["counts", "user:id,name"])
            ->whereBetween("closed_at", [$today->subDay()->startOfDay(), $today->subDay()->endOfDay()])->get()
            ->filter(fn (CashSession $s) => $s->totalDifference() < 0);
        if ($sessions->isEmpty()) {
            return null;
        }
        $total = $sessions->sum(fn (CashSession $s) => $s->totalDifference());

        return new Signal(
            "till_shortage",
            "warning",
            __("app.ai_d_till_shortage_title", ["amount" => money(abs($total))]),
            __("app.ai_d_till_shortage_body", [
                "count" => $sessions->count(),
                "list" => $sessions->map(fn ($s) => ($s->user?->name ?? "?") . " (" . money($s->totalDifference()) . ")")->implode(", "),
            ]),
            __("app.ai_d_till_shortage_tip"),
            ["sessions" => $sessions->count(), "total" => $total],
            route("cash-sessions.index"),
            "till_shortage:" . $today->subDay()->toDateString(),
        );
    }

    private function lowStock(CarbonImmutable $today): ?Signal
    {
        $items = InventoryItem::where("is_active", true)->where("min_stock_qty", ">", 0)
            ->whereColumn("stock_qty", "<=", "min_stock_qty")->orderBy("name")->get();
        if ($items->isEmpty()) {
            return null;
        }

        return new Signal(
            "low_stock",
            $items->contains(fn ($i) => (float) $i->stock_qty <= 0) ? "critical" : "warning",
            __("app.ai_d_low_stock_title", ["count" => $items->count()]),
            __("app.ai_d_low_stock_body", ["list" => $items->take(8)->map(fn ($i) => $i->name . " (" . (float) $i->stock_qty . " " . $i->unit . ")")->implode(", ")]),
            __("app.ai_d_low_stock_tip"),
            ["items" => $items->pluck("name")->all()],
            route("resources.items.index"),
            // Once a week while the situation lasts, or again when the list changes.
            "low_stock:" . $today->format("o-W") . ":" . md5($items->pluck("id")->implode(",")),
        );
    }

    private function expiringLots(CarbonImmutable $today): ?Signal
    {
        $lots = InventoryLot::expiringSoon(7)->where("remaining_qty", ">", 0)->with("item:id,name")->get();
        if ($lots->isEmpty()) {
            return null;
        }

        return new Signal(
            "expiring_lots",
            "info",
            __("app.ai_d_expiring_lots_title", ["count" => $lots->count()]),
            __("app.ai_d_expiring_lots_body", ["list" => $lots->take(8)->map(fn ($l) => ($l->item?->name ?? "?") . " — " . $l->expires_at?->format("Y-m-d"))->implode(", ")]),
            __("app.ai_d_expiring_lots_tip"),
            ["lots" => $lots->count()],
            route("resources.lots.index"),
            "expiring_lots:" . $today->format("o-W") . ":" . md5($lots->pluck("id")->implode(",")),
        );
    }

    private function overdueReceivables(CarbonImmutable $today): ?Signal
    {
        $orders = Order::overdueCredit()->with(["payments", "creditNotes"])->get();
        if ($orders->isEmpty()) {
            return null;
        }
        $total = $orders->sum(fn (Order $o) => max(0, $o->remainingBalance()));
        $customers = $orders->pluck("customer_id")->unique()->count();

        return new Signal(
            "overdue_receivables",
            $orders->contains(fn (Order $o) => $o->daysOverdue() > 60) ? "critical" : "warning",
            __("app.ai_d_overdue_receivables_title", ["amount" => money($total)]),
            __("app.ai_d_overdue_receivables_body", ["count" => $orders->count(), "customers" => $customers]),
            __("app.ai_d_overdue_receivables_tip"),
            ["orders" => $orders->count(), "customers" => $customers, "total" => $total],
            route("receivables.index"),
            "overdue_receivables:" . $today->format("o-W"),
        );
    }

    private function unclaimedOrders(CarbonImmutable $today): ?Signal
    {
        $orders = Order::unclaimed()->where("ready_at", "<=", $today->subDays(self::UNCLAIMED_DAYS))->get(["id", "total_price"]);
        if ($orders->isEmpty()) {
            return null;
        }

        return new Signal(
            "unclaimed_orders",
            "info",
            __("app.ai_d_unclaimed_title", ["count" => $orders->count()]),
            __("app.ai_d_unclaimed_body", ["count" => $orders->count(), "days" => self::UNCLAIMED_DAYS, "amount" => money($orders->sum("total_price"))]),
            __("app.ai_d_unclaimed_tip"),
            ["count" => $orders->count()],
            route("unclaimed.index"),
            "unclaimed_orders:" . $today->format("o-W"),
        );
    }

    private function failedMobilePayments(CarbonImmutable $today): ?Signal
    {
        $count = MobileTransaction::whereIn("status", ["failed", "expired"])
            ->whereBetween("created_at", [$today->subDay()->startOfDay(), $today->subDay()->endOfDay()])->count();
        if ($count < 3) {
            return null;
        }

        return new Signal(
            "failed_mobile_payments",
            "warning",
            __("app.ai_d_failed_mobile_title", ["count" => $count]),
            __("app.ai_d_failed_mobile_body", ["count" => $count]),
            __("app.ai_d_failed_mobile_tip"),
            ["count" => $count],
            route("mobile-payments.index"),
            "failed_mobile_payments:" . $today->subDay()->toDateString(),
        );
    }

    private function overdueMaintenance(CarbonImmutable $today): ?Signal
    {
        $jobs = MaintenanceJob::where("status", "scheduled")->whereDate("service_date", "<", $today)->with("equipment:id,name")->get();
        if ($jobs->isEmpty()) {
            return null;
        }

        return new Signal(
            "overdue_maintenance",
            "warning",
            __("app.ai_d_maintenance_title", ["count" => $jobs->count()]),
            __("app.ai_d_maintenance_body", ["list" => $jobs->take(8)->map(fn ($j) => ($j->equipment?->name ?? "?") . ": " . $j->title)->implode(", ")]),
            __("app.ai_d_maintenance_tip"),
            ["count" => $jobs->count()],
            route("resources.maintenance.index"),
            "overdue_maintenance:" . $today->format("o-W") . ":" . md5($jobs->pluck("id")->implode(",")),
        );
    }

    private function complaintSpike(CarbonImmutable $today): ?Signal
    {
        $week = Complaint::where("created_at", ">=", $today->subDays(7))->count();
        $before = Complaint::whereBetween("created_at", [$today->subDays(35), $today->subDays(7)])->count() / 4;
        if ($week < 3 || $week < 2 * max(1, $before)) {
            return null;
        }

        return new Signal(
            "complaint_spike",
            "warning",
            __("app.ai_d_complaints_title", ["count" => $week]),
            __("app.ai_d_complaints_body", ["count" => $week, "average" => round($before, 1)]),
            __("app.ai_d_complaints_tip"),
            ["last_7_days" => $week, "weekly_average" => round($before, 1)],
            route("complaints.index"),
            "complaint_spike:" . $today->format("o-W"),
        );
    }

    private function salesTrend(CarbonImmutable $today): ?Signal
    {
        $sum = fn (int $fromDays, int $toDays) => (float) Payment::whereNull("bounced_at")->whereBetween("paid_at", [$today->subDays($fromDays)->startOfDay(), $today->subDays($toDays)->endOfDay()])->sum("amount");
        $last = $sum(7, 1);
        $previous = $sum(14, 8);
        if ($previous <= 0) {
            return null;
        }
        $change = ($last - $previous) / $previous * 100;
        if (abs($change) < 20) {
            return null;
        }
        $up = $change > 0;

        return new Signal(
            "sales_trend",
            $up ? "info" : ($change <= -35 ? "critical" : "warning"),
            __($up ? "app.ai_d_sales_up_title" : "app.ai_d_sales_down_title", ["percent" => round(abs($change))]),
            __("app.ai_d_sales_trend_body", ["last" => money($last), "previous" => money($previous)]),
            __($up ? "app.ai_d_sales_up_tip" : "app.ai_d_sales_down_tip"),
            ["last_7_days" => $last, "previous_7_days" => $previous, "change_percent" => round($change, 1)],
            route("management.index"),
            "sales_trend:" . $today->format("o-W"),
        );
    }

    private function lostCustomers(CarbonImmutable $today): ?Signal
    {
        $count = Customer::whereHas("orders", fn ($o) => $o->whereBetween("received_at", [$today->subMonths(6), $today->subDays(45)]), ">=", 3)
            ->whereDoesntHave("orders", fn ($o) => $o->where("received_at", ">", $today->subDays(45)))->count();
        if ($count < 3) {
            return null;
        }

        return new Signal(
            "lost_customers",
            "info",
            __("app.ai_d_lost_customers_title", ["count" => $count]),
            __("app.ai_d_lost_customers_body", ["count" => $count]),
            __("app.ai_d_lost_customers_tip"),
            ["count" => $count],
            route("customers.index"),
            "lost_customers:" . $today->format("o-W"),
        );
    }

    private function expiringDocuments(CarbonImmutable $today): ?Signal
    {
        // Private documents (exchange) are not announced to the whole management.
        $documents = Document::where("is_private", false)->whereNotNull("expires_at")->where("expires_at", "<=", $today->addDays(Document::EXPIRING_DAYS))
            ->where("expires_at", ">=", $today->subDays(30))->orderBy("expires_at")->get(["id", "title", "expires_at"]);
        if ($documents->isEmpty()) {
            return null;
        }

        return new Signal(
            "expiring_documents",
            $documents->contains(fn ($d) => $d->expires_at->lt($today)) ? "warning" : "info",
            __("app.ai_d_documents_title", ["count" => $documents->count()]),
            __("app.ai_d_documents_body", ["list" => $documents->take(8)->map(fn ($d) => $d->title . " — " . $d->expires_at->format("Y-m-d"))->implode(", ")]),
            __("app.ai_d_documents_tip"),
            ["count" => $documents->count()],
            route("documents-archive.index", ["view" => "expiring"]),
            "expiring_documents:" . $today->format("o-W"),
        );
    }
}
