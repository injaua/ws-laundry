<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ["Laundry Admin", "admin@ws-cleanlaundry.example", "admin"],
            ["Laundry Cashier", "cashier@ws-cleanlaundry.example", "cashier"],
            ["Laundry Owner", "owner@ws-cleanlaundry.example", "owner"],
        ];

        // The well-known "password" is only acceptable on a developer machine or in tests.
        // Anywhere else each account gets a random password, printed once to the console.
        $useDefault = app()->environment("local", "testing");

        foreach ($accounts as [$name, $email, $role]) {
            $password = $useDefault ? "password" : Str::password(16);

            User::factory()->create([
                "name" => $name,
                "email" => $email,
                "password" => bcrypt($password),
                "role" => $role,
            ]);

            if (!$useDefault) {
                $this->command?->warn("{$email}: {$password}");
            }
        }

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
            MessageTemplateSeeder::class,
        ]);
    }
}
