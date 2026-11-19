<?php

namespace App\Services\Security;

use App\Models\ActivityLog;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Personal-data requests from a customer: a copy of the data held (export) and erasure
 * (anonymization). Orders, payments and fiscal documents stay, as the business must keep
 * them; only what identifies the person is removed.
 */
class CustomerPrivacy
{
    public function export(Customer $customer, User $user): array
    {
        $customer->loadMissing("orders.items.service", "orders.items.package", "orders.payments");

        $data = [
            "exported_at" => now()->toIso8601String(),
            "business" => config("app.name"),
            "customer" => $customer->only(["id", "name", "type", "legal_name", "nuit", "phone", "email", "address", "address_reference", "billing_address", "contact_person", "points", "whatsapp_opt_out", "created_at"]),
            "orders" => $customer->orders->map(fn (Order $o) => [
                "number" => $o->order_number,
                "received_at" => optional($o->received_at)->toDateString(),
                "status" => $o->status,
                "total" => (float) $o->total_price,
                "items" => $o->items->map(fn ($i) => ["service" => $i->service?->name ?? $i->package?->name, "type" => $i->type, "weight" => (float) $i->weight, "quantity" => (float) $i->amount, "unit_price" => (float) $i->unit_price, "total" => (float) $i->subtotal])->values(),
                "payments" => $o->payments->map(fn ($p) => ["date" => $p->paid_at->toDateString(), "method" => $p->method, "amount" => (float) $p->amount])->values(),
            ])->values(),
            "whatsapp_messages" => DB::table("whatsapp_messages")->where("customer_id", $customer->id)->orderBy("id")
                ->get(["direction", "body", "template_name", "status", "created_at"])->map(fn ($m) => (array) $m)->values(),
            "delivery_addresses" => DB::table("courier_jobs")->where("customer_id", $customer->id)->orderBy("id")
                ->get(["type", "contact_name", "contact_phone", "address", "address_reference", "scheduled_date"])->map(fn ($j) => (array) $j)->values(),
        ];

        ActivityLog::security("customer_exported", $customer, "Customer data exported: #{$customer->id}", $user);

        return $data;
    }

    /** Reasons that block erasure (empty = allowed). */
    public function blockers(Customer $customer): array
    {
        $reasons = [];
        $since = now()->subYears(SecuritySettings::fiscalRetentionYears());

        if ($customer->anonymized_at) {
            $reasons[] = __("app.privacy_already_anonymized");
        }
        if ($customer->orders()->whereNotIn("status", ["completed", "cancelled"])->exists()) {
            $reasons[] = __("app.privacy_open_orders");
        }
        if ($customer->orders->sum(fn (Order $o) => max(0, $o->remainingBalance())) > 0) {
            $reasons[] = __("app.privacy_open_balance");
        }
        // Fiscal documents issued to the customer by name / NUIT must be kept unchanged.
        $hasInvoices = Invoice::where("customer_id", $customer->id)->where("created_at", ">=", $since)->exists();
        $hasCreditNotes = CreditNote::whereIn("order_id", $customer->orders()->select("id"))->where("created_at", ">=", $since)->exists();
        if ($hasInvoices || $hasCreditNotes) {
            $reasons[] = __("app.privacy_fiscal_retention", ["years" => SecuritySettings::fiscalRetentionYears()]);
        }

        return $reasons;
    }

    public function anonymize(Customer $customer, User $user): void
    {
        if ($reasons = $this->blockers($customer)) {
            throw ValidationException::withMessages(["customer" => implode(" ", $reasons)]);
        }

        DB::transaction(function () use ($customer, $user) {
            $id = $customer->id;
            $removed = "[" . __("app.privacy_removed") . "]";

            $customer->forceFill([
                "name" => __("app.privacy_anonymous_customer", ["id" => $id]),
                "phone" => "ANON-{$id}",
                "email" => null, "address" => null, "address_reference" => null, "billing_address" => null,
                "legal_name" => null, "nuit" => null, "contact_person" => null,
                "whatsapp_opt_out" => true,
                "anonymized_at" => now(),
            ])->saveQuietly();

            DB::table("whatsapp_messages")->where("customer_id", $id)->update(["phone" => "ANON-{$id}", "body" => $removed, "meta" => null]);
            DB::table("customer_message_logs")->where("customer_id", $id)->update(["phone" => "ANON-{$id}", "body" => $removed]);
            DB::table("courier_jobs")->where("customer_id", $id)->update(["contact_name" => null, "contact_phone" => null, "address" => $removed, "address_reference" => null]);

            // The trail keeps that the erasure happened, not the erased data.
            ActivityLog::security("customer_anonymized", $customer, "Customer anonymized: #{$id}", $user);
        });
    }
}
