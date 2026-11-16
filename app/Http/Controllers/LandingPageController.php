<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Order;
use App\Models\Package;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Testimonial;
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

        return view("landing.index", compact(
            "settings", "services", "packages", "testimonials", "galleries"
        ));
    }

    public function checkStatus(Request $request)
    {
        $settings = SiteSetting::allAsArray();
        $order = null;
        $notFound = false;

        $raw = $request->query("order_number");
        $orderNumber = is_string($raw) ? strtoupper(preg_replace('/\s+/', "", $raw)) : "";

        if ($orderNumber !== "") {
            $order = Order::with(["customer", "items.service", "items.package", "payments"])
                ->whereRaw("upper(order_number) = ?", [$orderNumber])
                ->first();

            if (!$order) {
                $notFound = true;
            }
        }

        return view("landing.order-status", compact(
            "settings", "order", "notFound", "orderNumber"
        ));
    }
}
