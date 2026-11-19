<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Security\CustomerPrivacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Data-protection requests from a customer: copy of the data and erasure. */
class CustomerPrivacyController extends Controller
{
    public function __construct(private CustomerPrivacy $privacy) {}

    public function export(Request $request, Customer $customer): JsonResponse
    {
        $data = $this->privacy->export($customer, $request->user());
        $name = "dados-cliente-{$customer->id}-" . now()->format("Ymd") . ".json";

        return response()->json($data, 200, ["Content-Disposition" => "attachment; filename=\"{$name}\""], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function anonymize(Request $request, Customer $customer): RedirectResponse
    {
        $request->validate([
            "password" => ["required", "current_password"],
            "confirm" => ["required", "accepted"],
        ]);

        $this->privacy->anonymize($customer, $request->user());

        return redirect()->route("customers.show", $customer)->with("success", __("app.privacy_anonymized_done"));
    }
}
