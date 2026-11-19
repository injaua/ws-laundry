<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::with("items.service", "items.package")->latest("starts_on")->latest("id")->get();

        // Usage: lines sold under each promotion and the money given away.
        $usage = OrderItem::whereNotNull("promotion_id")
            ->whereHas("order")
            ->get()
            ->groupBy("promotion_id")
            ->map(fn ($items) => [
                "orders" => $items->pluck("order_id")->unique()->count(),
                "discount" => (float) $items->sum(fn (OrderItem $item) => $item->discountAmount()),
            ]);

        return view("promotions.index", compact("promotions", "usage"));
    }

    public function create()
    {
        return view("promotions.create", $this->formData(new Promotion([
            "discount_type" => "percent",
            "applies_to" => "selected",
            "starts_on" => today(),
            "is_active" => true,
        ])));
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $promotion = Promotion::create($this->validated($request) + ["created_by" => $request->user()->id]);
            $this->syncItems($promotion, $request);
        });

        return redirect()->route("promotions.index")->with("success", __("app.promotion_saved"));
    }

    public function edit(Promotion $promotion)
    {
        return view("promotions.edit", $this->formData($promotion->load("items")));
    }

    public function update(Request $request, Promotion $promotion)
    {
        DB::transaction(function () use ($request, $promotion) {
            $promotion->update($this->validated($request));
            $this->syncItems($promotion, $request);
        });

        return redirect()->route("promotions.index")->with("success", __("app.promotion_saved"));
    }

    /** Promotions already used on orders are deactivated, not deleted, to keep the history. */
    public function destroy(Promotion $promotion)
    {
        if ($promotion->orderItems()->exists()) {
            $promotion->update(["is_active" => false]);

            return redirect()->route("promotions.index")->with("success", __("app.promotion_deactivated"));
        }

        $promotion->delete();

        return redirect()->route("promotions.index")->with("success", __("app.promotion_deleted"));
    }

    private function formData(Promotion $promotion): array
    {
        return [
            "promotion" => $promotion,
            "services" => Service::orderBy("name")->get(),
            "packages" => Package::orderBy("name")->get(),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            "name" => "required|string|max:100",
            "description" => "nullable|string|max:1000",
            "discount_type" => ["required", Rule::in(Promotion::TYPES)],
            "value" => ["required", "numeric", "min:0.01", $request->discount_type === "percent" ? "max:100" : "max:100000000"],
            "applies_to" => ["required", Rule::in(Promotion::SCOPES)],
            "services" => "nullable|array",
            "services.*" => "integer|exists:services,id",
            "packages" => "nullable|array",
            "packages.*" => "integer|exists:packages,id",
            "starts_on" => "required|date",
            "ends_on" => "nullable|date|after_or_equal:starts_on",
            "weekdays" => "nullable|array",
            "weekdays.*" => "integer|between:0,6",
            "is_active" => "boolean",
        ]);

        if ($data["applies_to"] === "selected" && empty($data["services"]) && empty($data["packages"])) {
            throw ValidationException::withMessages(["services" => __("app.promotion_needs_items")]);
        }

        $weekdays = array_values(array_unique(array_map("intval", $data["weekdays"] ?? [])));
        sort($weekdays);

        return [
            "name" => $data["name"],
            "description" => $data["description"] ?? null,
            "discount_type" => $data["discount_type"],
            "value" => $data["value"],
            "applies_to" => $data["applies_to"],
            "starts_on" => $data["starts_on"],
            "ends_on" => $data["ends_on"] ?? null,
            // Every day selected is the same as no restriction.
            "weekdays" => $weekdays === [] || count($weekdays) === 7 ? null : $weekdays,
            "is_active" => $request->boolean("is_active"),
        ];
    }

    private function syncItems(Promotion $promotion, Request $request): void
    {
        $promotion->items()->delete();

        if ($promotion->applies_to !== "selected") {
            return;
        }

        foreach (array_unique((array) $request->input("services", [])) as $id) {
            $promotion->items()->create(["service_id" => (int) $id]);
        }

        foreach (array_unique((array) $request->input("packages", [])) as $id) {
            $promotion->items()->create(["package_id" => (int) $id]);
        }
    }
}
