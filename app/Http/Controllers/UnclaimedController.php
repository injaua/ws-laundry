<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\AutomaticNotifications;
use App\Services\WhatsApp\Messenger;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Peças não levantadas: orders ready but never collected. Shows how long each has
 * waited, the reminders already sent, and lets management record what was done
 * with the garments once the waiting period is over (donated, discarded…).
 */
class UnclaimedController extends Controller
{
    /** Days an order waits before the shop may dispose of it. */
    public const DEFAULT_ABANDON_DAYS = 90;

    public const BUCKETS = [[0, 7], [7, 30], [30, 60], [60, 90], [90, null]];

    public static function abandonDays(): int
    {
        return max(7, (int) SiteSetting::get("unclaimed_abandon_days", self::DEFAULT_ABANDON_DAYS));
    }

    public function index(Request $request): View
    {
        Gate::authorize("viewAny", Order::class);

        $tab = $request->query("tab") === "abandoned" ? "abandoned" : "waiting";
        $bucket = $request->query("bucket");

        $orders = $tab === "abandoned"
            ? Order::with(["customer:id,name,phone", "abandoner:id,name"])->withCount("pieces")
                ->whereNotNull("abandoned_at")->latest("abandoned_at")->paginate(25)->withQueryString()
            : Order::with("customer:id,name,phone,whatsapp_opt_out")->withCount("pieces")
                ->unclaimed()
                ->when($bucket !== null && isset(self::BUCKETS[(int) $bucket]), function ($q) use ($bucket) {
                    [$min, $max] = self::BUCKETS[(int) $bucket];
                    $q->where("ready_at", "<=", now()->subDays($min));
                    if ($max !== null) {
                        $q->where("ready_at", ">", now()->subDays($max));
                    }
                })
                ->orderBy("ready_at")
                ->paginate(25)->withQueryString();

        // Last reminder (automatic or manual) per order on this page.
        $lastReminder = WhatsAppMessage::whereIn("order_id", $orders->pluck("id"))
            ->where("direction", WhatsAppMessage::OUT)->where("status", "!=", "failed")
            ->selectRaw("order_id, max(created_at) as at, count(*) as total")->groupBy("order_id")->get()->keyBy("order_id");

        $waiting = Order::unclaimed()->get(["id", "ready_at", "total_price"]);
        $counts = collect(self::BUCKETS)->map(fn ($b) => $waiting->filter(function ($o) use ($b) {
            $days = $o->ready_at->diffInDays(now());

            return $days >= $b[0] && ($b[1] === null || $days < $b[1]);
        })->count());

        return view("unclaimed.index", [
            "orders" => $orders,
            "tab" => $tab,
            "bucket" => $bucket,
            "counts" => $counts,
            "lastReminder" => $lastReminder,
            "abandonDays" => self::abandonDays(),
            "waitingValue" => (float) $waiting->sum("total_price"),
            "canManage" => $request->user()->can("edit", new Order),
            "reminderReady" => app(AutomaticNotifications::class)->template("unclaimed") !== null,
        ]);
    }

    /** Send the "items waiting" message now (the daily job also sends it automatically). */
    public function remind(Request $request, Order $order, AutomaticNotifications $notifications, Messenger $messenger): RedirectResponse
    {
        Gate::authorize("update", $order);

        $template = $notifications->template("unclaimed");
        if (!$template) {
            return back()->with("error", __("app.unclaimed_no_template"));
        }
        if (!$order->customer || $order->customer->normalizedWhatsapp() === null) {
            return back()->with("error", __("app.customer_without_phone"));
        }

        $days = $order->ready_at ? (int) $order->ready_at->diffInDays(now()) : 0;

        try {
            $messenger->sendTemplate($template, $order->customer, $notifications->variables($order, ["days" => $days]), $request->user(), $order);
        } catch (WhatsAppException $e) {
            return back()->with("error", $e->getMessage());
        }

        return back()->with("success", __("app.unclaimed_reminded", ["order" => $order->order_number]));
    }

    /** Record what was done with garments never collected. */
    public function abandon(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize("edit", $order);

        $data = $request->validate([
            "abandon_destination" => ["required", Rule::in(Order::ABANDON_DESTINATIONS)],
            "abandon_note" => "nullable|string|max:255",
        ]);

        if (!in_array($order->status, ["ready", "delivering"], true) || $order->abandoned_at) {
            return back()->with("error", __("app.unclaimed_not_waiting"));
        }
        $days = $order->ready_at ? $order->ready_at->diffInDays(now()) : 0;
        if ($days < self::abandonDays()) {
            return back()->with("error", __("app.unclaimed_too_early", ["days" => self::abandonDays()]));
        }

        $order->update($data + ["abandoned_at" => now(), "abandoned_by" => $request->user()->id]);

        return back()->with("success", __("app.unclaimed_abandoned", ["order" => $order->order_number]));
    }

    /** The customer turned up after all: the order goes back to the waiting list. */
    public function restore(Order $order): RedirectResponse
    {
        Gate::authorize("edit", $order);

        $order->update(["abandoned_at" => null, "abandon_destination" => null, "abandon_note" => null, "abandoned_by" => null]);

        return back()->with("success", __("app.unclaimed_restored", ["order" => $order->order_number]));
    }

    public function settings(Request $request): RedirectResponse
    {
        Gate::authorize("edit", new Order);

        $data = $request->validate(["unclaimed_abandon_days" => "required|integer|min:7|max:730"]);
        SiteSetting::set("unclaimed_abandon_days", (int) $data["unclaimed_abandon_days"]);

        return back()->with("success", __("app.settings_saved"));
    }
}
