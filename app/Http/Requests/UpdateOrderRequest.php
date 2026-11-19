<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can("update", $this->route("order")) ?? false;
    }

    public function rules(): array
    {
        return [
            "customer_id" => "required|exists:customers,id",
            "status" => "required|in:" . implode(",", Order::STATUSES),
            "received_at" => "required|date",
            "delivery_date" => "nullable|date|after_or_equal:received_at",
            "completed_at" => "nullable|date",
            "notes" => "nullable|string",
            "purchase_order_ref" => "nullable|string|max:60",
        ];
    }
}
