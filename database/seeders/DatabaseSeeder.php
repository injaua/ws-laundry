<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            "name" => "Laundry Admin",
            "email" => "admin@ws-cleanlaundry.example",
            "password" => bcrypt("password"),
            "role" => "admin",
        ]);

        User::factory()->create([
            "name" => "Laundry Cashier",
            "email" => "cashier@ws-cleanlaundry.example",
            "password" => bcrypt("password"),
            "role" => "cashier",
        ]);

        User::factory()->create([
            "name" => "Laundry Owner",
            "email" => "owner@ws-cleanlaundry.example",
            "password" => bcrypt("password"),
            "role" => "owner",
        ]);

        // Default site settings for landing page
        $defaults = [
            "hero_title" => "A lavandaria de confiança na sua cidade",
            "hero_subtitle" => "A sua roupa limpa, perfumada e bem tratada, com serviços profissionais.",
            "hero_cta_text" => "Encomendar",
            "hero_cta_url" => "https://wa.me/258867314502",
            "about_title" => "Sobre a WS Clean & Laundry Serviços, Lda",
            "about_content" => "A WS Clean & Laundry Serviços, Lda é um serviço de lavandaria profissional que oferece lavagem a seco, engomadoria e lavagem expresso. Comprometemo-nos a proporcionar o melhor resultado para as suas peças preferidas, com um atendimento simpático e preços acessíveis.",
            "whatsapp_number" => "258867314502",
            "address" => "Matola, Bairro da Matola Gare, Avenida Josina Machel",
            "footer_text" => "\u00a9 " . date("Y") . " WS Clean & Laundry Serviços, Lda. All rights reserved.",
        ];

        foreach ($defaults as $key => $value) {
            SiteSetting::set($key, $value);
        }

        $this->call([
            ServiceSeeder::class,
            CustomerSeeder::class,
            EmployeeSeeder::class,
        ]);
    }
}
