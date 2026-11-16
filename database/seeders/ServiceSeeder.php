<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ["name" => "Dry Cleaning", "type" => "by_weight", "price" => 5000, "estimated_days" => 2],
            ["name" => "Dry Cleaning + Ironing", "type" => "by_weight", "price" => 8000, "estimated_days" => 2],
            ["name" => "Wet Wash", "type" => "by_weight", "price" => 4000, "estimated_days" => 1],
            ["name" => "Ironing Only", "type" => "by_weight", "price" => 5000, "estimated_days" => 1],
            ["name" => "Shirt", "type" => "by_piece", "price" => 10000, "estimated_days" => 2],
            ["name" => "Trousers", "type" => "by_piece", "price" => 8000, "estimated_days" => 2],
            ["name" => "Blanket", "type" => "by_piece", "price" => 15000, "estimated_days" => 3],
            ["name" => "Suit / Blazer", "type" => "by_piece", "price" => 25000, "estimated_days" => 3],
            ["name" => "Duvet Cover", "type" => "by_piece", "price" => 20000, "estimated_days" => 3],
            ["name" => "Carpet", "type" => "by_weight", "price" => 12000, "estimated_days" => 4],
        ];

        foreach ($services as $svc) {
            Service::create($svc);
        }

        $this->command->info("10 services created.");
    }
}
