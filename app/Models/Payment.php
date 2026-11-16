<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = ["cash", "transfer", "mpesa", "emola", "mkesh"];

    protected $fillable = ["order_id", "user_id", "paid_at", "amount", "method", "note"];

    protected function casts(): array
    {
        return [
            "amount" => "decimal:0",
            "paid_at" => "date",
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Debit Caixa (1.1) or Depósitos à ordem (1.2.1); credit Prestação de serviços (7.2)
     * with the net part and, when the order carries VAT, IVA liquidado (4.4.3.3) with the
     * tax part. Returns null when the chart of accounts is missing a needed account.
     */
    public function postToJournal(): ?JournalEntry
    {
        $this->loadMissing("order");
        $order = $this->order;

        if (!$order) {
            return null;
        }

        $debit = Account::findByCode($this->method === "cash" ? "1.1" : "1.2.1")?->id;
        $services = Account::findByCode("7.2")?->id;
        $amount = (float) $this->amount;
        $description = __("app.payment") . " " . $order->order_number;
        $date = $this->paid_at->toDateString();

        $rate = (float) $order->iva_rate;
        if ($rate > 0 && $services && $debit) {
            if ($iva = Account::findByCode("4.4.3.3")?->id) {
                [$net, $ivaPart] = $order->splitGrossAmount($amount);

                $entry = JournalEntry::postLines([
                    ["account_id" => $debit, "debit" => $amount, "credit" => 0],
                    ["account_id" => $services, "debit" => 0, "credit" => $net],
                    ["account_id" => $iva, "debit" => 0, "credit" => $ivaPart],
                ], $date, $description, "payment", $this->id);

                if ($entry) {
                    return $entry;
                }
            }
        }

        return JournalEntry::postPair($debit, $services, $amount, $date, $description, "payment", $this->id);
    }
}
