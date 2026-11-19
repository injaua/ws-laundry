<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Templates used by the automatic notifications that did not exist yet. */
    private const TEMPLATES = [
        "Encomenda recebida" => "Olá {customer}! Recebemos a sua encomenda {order}. Entrega prevista: {delivery_date}. Obrigado pela preferência!",
        "Pagamento recebido" => "Olá {customer}, confirmamos o pagamento de {amount} Mts referente à encomenda {order}. Obrigado!",
        "Peças por levantar" => "Olá {customer}, a sua encomenda {order} está pronta há {days} dias e aguarda levantamento na WS Clean & Laundry. Esperamos por si!",
    ];

    public function up(): void
    {
        foreach (self::TEMPLATES as $name => $body) {
            if (!DB::table("message_templates")->where("name", $name)->exists()) {
                DB::table("message_templates")->insert([
                    "name" => $name, "body" => $body, "is_active" => true,
                    "created_at" => now(), "updated_at" => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table("message_templates")->whereIn("name", array_keys(self::TEMPLATES))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from("whatsapp_messages")->whereColumn("whatsapp_messages.template_id", "message_templates.id"))
            ->delete();
    }
};
