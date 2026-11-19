<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared by customers.store and customers.update. */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role middleware on the route group decides who reaches this
    }

    public function rules(): array
    {
        $customer = $this->route("customer");

        return [
            "name" => "required|string|max:100",
            "phone" => ["required", "string", "max:20", Rule::unique("customers")->ignore($customer?->id)],
            "address" => "nullable|string",
            "delivery_zone_id" => "nullable|exists:delivery_zones,id",
            "address_reference" => "nullable|string|max:255",
            "is_credit_customer" => "boolean",
            "whatsapp_opt_out" => "nullable|boolean",
            "credit_limit" => "nullable|numeric|min:0",
            "credit_terms_days" => "nullable|integer|min:1|max:365",
            "type" => ["nullable", Rule::in(Customer::TYPES)],
            // Business customers need their registered name and NUIT (9 digits) on invoices.
            "legal_name" => "nullable|required_if:type,business|string|max:150",
            "nuit" => ["nullable", "required_if:type,business", "regex:/^\d{9}$/", Rule::unique("customers")->ignore($customer?->id)],
            "email" => "nullable|email|max:150",
            "contact_person" => "nullable|string|max:100",
            "billing_address" => "nullable|string",
            "billing_cycle" => ["nullable", Rule::in(Customer::BILLING_CYCLES)],
            "discount_percent" => "nullable|numeric|min:0|max:100",
        ];
    }

    /** Validated data with the credit fields normalised to their stored types. */
    public function customerData(): array
    {
        $data = $this->validated();
        $customer = $this->route("customer");

        $data["is_credit_customer"] = $this->boolean("is_credit_customer");
        $data["whatsapp_opt_out"] = $this->boolean("whatsapp_opt_out");
        $data["credit_limit"] = (float) ($data["credit_limit"] ?? 0);
        $data["credit_terms_days"] = (int) ($data["credit_terms_days"] ?? $customer?->credit_terms_days ?? 30);
        $data["type"] = $data["type"] ?? $customer?->type ?? "individual";
        $data["billing_cycle"] = $data["is_credit_customer"] ? ($data["billing_cycle"] ?? $customer?->billing_cycle ?? "per_order") : "per_order";
        $data["discount_percent"] = (float) ($data["discount_percent"] ?? $customer?->discount_percent ?? 0);

        // Commercial conditions are a management decision: cashiers cannot grant discounts.
        if ($this->user()?->isCashier()) {
            unset($data["discount_percent"]);
        }

        // Company fields only make sense for business customers.
        if ($data["type"] !== "business") {
            $data["legal_name"] = null;
            $data["nuit"] = null;
        }

        return $data;
    }
}
