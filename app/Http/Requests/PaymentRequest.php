<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAmounts;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\Cheques;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A manual payment at the counter: payments.store and payments.update.
 *
 * Validates the fields, the accounting period, the operator reference (unique per
 * method, a cheque never taken twice), the tendered cash and whether the customer may
 * pay by cheque. The order-level rules (order locked, nothing left to pay, amount above
 * what is still owed, till required) stay in the controller: they depend on the state
 * of the order, not on the input.
 */
class PaymentRequest extends FormRequest
{
    use ValidatesAmounts;

    /** Normalised payment data, filled once validation passed. */
    private array $paymentData = [];

    private ?Order $order = null;

    public function authorize(): bool
    {
        return true; // role middleware on the route group and the controller locks decide
    }

    public function rules(): array
    {
        return [
            ...($this->payment() ? [] : ["order_id" => ["required", "integer", Rule::exists("orders", "id")]]),
            "paid_at" => ["required", "date"],
            "amount" => $this->amountRules("1"),
            "method" => ["required", Rule::in(Payment::acceptedMethods($this->payment()?->method))],
            "note" => ["nullable", "string", "max:255"],
            ...Payment::referenceRules(),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Changing a payment touches the period it was in and the one it moves to.
                if ($payment = $this->payment()) {
                    $this->rejectClosedPeriod($validator, $payment->paid_at, "paid_at");
                }
                $this->rejectClosedPeriod($validator, $this->input("paid_at"), "paid_at");
            },
        ];
    }

    /** Reference, tendered cash and cheque checks: they need the validated data. */
    protected function passedValidation(): void
    {
        $data = $this->validated();
        $payment = $this->payment();

        if ($payment) {
            if ($data["method"] === "cheque" && !$payment->isCheque()) {
                Cheques::assertAccepted($payment->order?->customer, "cheque");
            }
        } else {
            Cheques::assertAccepted($this->order()->customer, $data["method"]);
        }

        // Payments of a grouped receipt (one transfer over several orders) keep sharing their reference.
        if ($payment?->group_ref && in_array($data["method"], Payment::UNIQUE_REF_METHODS, true)
            && Payment::normalizeRef($data["transaction_ref"] ?? null) === $payment->transaction_ref) {
            $data["transaction_ref"] = $payment->transaction_ref;
        } else {
            $data = Payment::validateReference($data, $payment?->id);
        }

        $this->paymentData = $data;
    }

    /** Validated and normalised fields, ready for Payment::create() / update(). */
    public function paymentData(): array
    {
        return $this->paymentData;
    }

    /** The order being paid (payments.store). */
    public function order(): Order
    {
        return $this->order ??= Order::with("customer")->findOrFail($this->input("order_id"));
    }

    /** The payment being edited (payments.update), null when recording a new one. */
    public function payment(): ?Payment
    {
        $payment = $this->route("payment");

        return $payment instanceof Payment ? $payment : null;
    }
}
