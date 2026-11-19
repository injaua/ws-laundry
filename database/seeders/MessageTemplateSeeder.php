<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                "name" => "Pronto para recolher",
                "body" => "Olá {customer}! A sua encomenda {order} já está pronta para recolher na WS Clean & Laundry. Obrigado pela preferência!",
            ],
            [
                "name" => "Encomenda recebida",
                "body" => "Olá {customer}! Recebemos a sua encomenda {order}. Entrega prevista: {delivery_date}. Obrigado pela preferência!",
            ],
            [
                "name" => "Pagamento recebido",
                "body" => "Olá {customer}, confirmamos o pagamento de {amount} Mts referente à encomenda {order}. Obrigado!",
            ],
            [
                "name" => "Peças por levantar",
                "body" => "Olá {customer}, a sua encomenda {order} está pronta há {days} dias e aguarda levantamento na WS Clean & Laundry. Esperamos por si!",
            ],
            [
                "name" => "Atraso na entrega",
                "body" => "Olá {customer}, informamos que a sua encomenda {order} terá um pequeno atraso. Pedimos desculpa pelo incómodo. Estamos à disposição.",
            ],
            [
                "name" => "Fatura vencida",
                "body" => "Caro(a) {customer}, a sua fatura {order} no valor de {amount} Mts venceu em {due_date}. Agradecemos o pagamento. Obrigado!",
            ],
            [
                "name" => "Lembrete de pagamento",
                "body" => "Caro(a) {customer}, lembramos que a fatura {order} ({amount} Mts) vence em {due_date}. Qualquer dúvida, estamos à disposição.",
            ],
            [
                "name" => "Promoção",
                "body" => "Olá {customer}! Esta semana a WS Clean & Laundry tem 10% de desconto em lavagem e engomadoria. Aproveite e visite-nos!",
            ],
            [
                "name" => "Agradecimento",
                "body" => "Obrigado, {customer}! Esperamos que tenha ficado satisfeito com o nosso serviço. Voltamos a recebê-lo quando precisar.",
            ],
        ];

        foreach ($templates as $tpl) {
            MessageTemplate::firstOrCreate(["name" => $tpl["name"]], ["body" => $tpl["body"]]);
        }
    }
}
