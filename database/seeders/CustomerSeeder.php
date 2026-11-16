<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ["name" => "Domingo Sitoe", "phone" => "+258841000100", "address" => "Av. Julius Nyerere No. 10, Maputo"],
            ["name" => "Lurdes Matsinhe", "phone" => "+258842000200", "address" => "Av. 24 de Julho No. 25, Maputo"],
            ["name" => "Carlos Machava", "phone" => "+258843000300", "address" => "Av. do Brasil No. 5, Matola"],
            ["name" => "Isabel Mondlane", "phone" => "+258844000400", "address" => "Rua da Missão No. 12, Maputo"],
            ["name" => "Joaquim Tembe", "phone" => "+258845000500", "address" => "Av. Moisés Goulart No. 8, Beira"],
            ["name" => "Amélia Cumbe", "phone" => "+258846000600", "address" => "Rua Consentino No. 3, Nampula"],
            ["name" => "Nelson Cossa", "phone" => "+258847000700", "address" => "Av. Edgar Mondlane No. 15, Xai-Xai"],
            ["name" => "Sónia Macuácua", "phone" => "+258848000800", "address" => "Rua da Solidariedade No. 7, Tete"],
            ["name" => "Paulo Zucula", "phone" => "+258849000900", "address" => "Av. das Indústrias No. 20, Matola"],
            ["name" => "Verónica Chipala", "phone" => "+258841001001", "address" => "Rua Kim Pa No. 9, Quelimane"],
        ];

        foreach ($customers as $c) {
            Customer::create($c);
        }

        $this->command->info("10 customers created.");
    }
}
