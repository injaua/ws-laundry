<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can("create", Order::class) ?? false;
    }

    public function rules(): array
    {
        return [
            "customer_id" => "required|exists:customers,id",
            "received_at" => "required|date",
            "delivery_date" => "nullable|date|after_or_equal:received_at",
            "notes" => "nullable|string",
            "items" => "required|array|min:1",
            "items.*.service_id" => "nullable|exists:services,id",
            "items.*.package_id" => "nullable|exists:packages,id",
            "items.*.weight" => "nullable|numeric|min:0.5",
            "items.*.amount" => "nullable|integer|min:1",
            "is_credit" => "boolean",
            "credit_override_note" => "nullable|string|max:255",
            "purchase_order_ref" => "nullable|string|max:60",
            "is_express" => "boolean",
            "pickup_job_id" => "nullable|integer|exists:courier_jobs,id",
            "care_flags" => "nullable|array",
            "care_flags.*" => ["string", Rule::in(Order::CARE_FLAGS)],
            "intake_notes" => "nullable|string|max:1000",
            "photos" => "nullable|array|max:10",
            "photos.*" => "image|max:5120",
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ($this->orderItems() as $key => $item) {
                    if (!is_array($item) || (empty($item["service_id"]) && empty($item["package_id"]))) {
                        $validator->errors()->add("items.$key.service_id", __("app.select_service_in_every_item"));
                    }
                }
            },
        ];
    }

    /**
     * Raw item rows. validated() may drop an item that carries no rule-bearing keys,
     * so the items are read from the input (they are re-priced from the catalog anyway).
     */
    public function orderItems(): array
    {
        $items = $this->input("items", []);

        return is_array($items) ? $items : [];
    }
}
