<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\Gallery;
use App\Models\Order;
use App\Models\Package;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Store;
use App\Models\Testimonial;
use App\Support\CurrentStore;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    public function index()
    {
        $settings = SiteSetting::allAsArray();
        $services = Service::orderBy("name")->get();
        $packages = Package::where("is_active", true)->orderBy("name")->get();
        $testimonials = Testimonial::active()->get();
        $galleries = Gallery::active()->get();
        // The pickup form appears once at least one delivery zone is active.
        $zones = DeliveryZone::active()->get();
        // With several stores the visitor chooses where the pickup request goes.
        $stores = Store::active()->orderBy("id")->get();
        $publicStore = CurrentStore::store();

        return view("landing.index", compact(
            "settings", "services", "packages", "testimonials", "galleries", "zones", "stores", "publicStore"
        ));
    }

    public function checkStatus(Request $request)
    {
        $settings = SiteSetting::allAsArray();
        $order = null;
        $notFound = false;

        $raw = $request->query("order_number");
        $orderNumber = is_string($raw) ? strtoupper(preg_replace('/\s+/', "", $raw)) : "";

        $rawPhone = $request->query("phone_last4");
        $phoneLast4 = is_string($rawPhone) ? preg_replace('/\D/', "", $rawPhone) : "";

        if ($orderNumber !== "") {
            // Order numbers are sequential, so the number alone must not reveal the
            // customer's name and payments: the last 4 phone digits act as a second
            // factor. A wrong phone is reported exactly like an unknown number.
            // Each store numbers its own orders, so the same number can exist in two
            // stores: the phone tells them apart. Everything is read in the order's store.
            $order = Order::acrossStores()
                ->whereRaw("upper(order_number) = ?", [$orderNumber])
                ->get()
                ->first(fn (Order $candidate) => CurrentStore::run($candidate->store_id, fn () => (bool) $candidate->customer?->phoneEndsWith($phoneLast4)));

            if ($order) {
                CurrentStore::run($order->store_id, fn () => $order->load(["customer", "items.service", "items.package", "payments", "pieces:id,order_id,status"]));
            }

            if (!$order) {
                $notFound = true;
            }
        }

        return view("landing.order-status", compact(
            "settings", "order", "notFound", "orderNumber"
        ));
    }
}
