<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MobileTransaction;
use App\Models\Order;
use App\Services\MobileMoney\MobileMoneySettings;
use App\Services\MobileMoney\MobilePayments;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customers pay by themselves: from the link in the WhatsApp message (signed URL) or
 * on the website, finding the order by its number and the customer's phone.
 */
class PublicPaymentController extends Controller
{
    public function lookupForm(): View
    {
        abort_unless(MobileMoneySettings::enabledAnywhere(), 404);

        return view("pay.lookup");
    }

    public function lookup(Request $request): RedirectResponse
    {
        abort_unless(MobileMoneySettings::enabledAnywhere(), 404);

        $data = $request->validate([
            "order_number" => "required|string|max:40",
            "phone" => "required|string|max:20",
            "website" => "nullable|max:0", // honeypot
        ]);

        $digits = substr(preg_replace("/\D/", "", $data["phone"]), -9);

        // Each store numbers its own orders: the same number may exist in two stores, the
        // phone tells them apart. Same answer whether the order does not exist or the phone does not match.
        $order = strlen($digits) !== 9 ? null : Order::acrossStores()
            ->where("order_number", trim($data["order_number"]))
            ->get()
            ->first(fn (Order $candidate) => CurrentStore::run($candidate->store_id, fn () => substr(preg_replace("/\D/", "", (string) $candidate->customer?->phone), -9) === $digits));

        if (!$order) {
            return back()->withInput()->withErrors(["order_number" => __("app.pay_lookup_not_found")]);
        }

        return redirect()->to($order->payLink());
    }

    public function show(Order $order): View
    {
        return view("pay.show", [
            "order" => $order->load("customer"),
            "remaining" => max(0, round($order->remainingBalance(), 2)),
            "providers" => MobileMoneySettings::enabledProviders(),
            "requestUrl" => URL::temporarySignedRoute("pay.request", now()->addHours(2), ["order" => $order->id]),
            "phone" => $order->customer?->phone,
        ]);
    }

    public function request(Request $request, Order $order, MobilePayments $payments): RedirectResponse
    {
        $data = $request->validate([
            "provider" => ["required", Rule::in(MobileTransaction::PROVIDERS)],
            "phone" => "required|string|max:20",
        ]);

        // The customer pays the whole balance (no partial amounts from the public page).
        $tx = $payments->request($order, $data["provider"], $data["phone"], null, null, "link");

        return redirect()->to(URL::temporarySignedRoute("pay.status-page", now()->addHours(2), ["transaction" => $tx->reference]));
    }

    public function statusPage(string $transaction): View
    {
        $tx = MobileTransaction::where("reference", $transaction)->where("channel", "link")->firstOrFail();

        return view("pay.status", [
            "tx" => $tx->load("order"),
            "statusUrl" => URL::temporarySignedRoute("pay.status", now()->addHours(2), ["transaction" => $tx->reference]),
            "backUrl" => $tx->order?->payLink(),
        ]);
    }

    public function status(string $transaction): JsonResponse
    {
        $tx = MobileTransaction::where("reference", $transaction)->where("channel", "link")->firstOrFail();

        return response()->json([
            "status" => $tx->status,
            "status_label" => __("app.mm_status_" . $tx->status),
            "open" => $tx->isOpen(),
            "message" => in_array($tx->status, ["failed", "expired"], true) ? __("app.mm_public_failed") : null,
        ]);
    }
}
