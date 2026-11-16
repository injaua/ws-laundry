<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // Every Admin/Owner/Cashier account is also an employee — create a
        // record for any that doesn't have one yet (works with real emails).
        $rolePositions = [
            "admin" => ["Administrador de Sistemas", 45000],
            "owner" => ["Diretor Geral", 80000],
            "cashier" => ["Caixa de Loja", 18000],
        ];

        foreach ($rolePositions as $role => [$position, $salary]) {
            $users = User::where("role", $role)
                ->whereDoesntHave("employee")
                ->get();

            foreach ($users as $user) {
                Employee::create([
                    "name" => $user->name,
                    "position" => $position,
                    "base_salary" => $salary,
                    "hire_date" => $user->created_at->toDateString(),
                    "status" => "active",
                    "contract_type" => "full_time",
                    "user_id" => $user->id,
                ]);
            }
        }

        $externalStaff = [
            ["name" => "Guarda Noturno", "position" => "Guarda", "base_salary" => 10000],
            ["name" => "Motorista de Entregas", "position" => "Motorista", "base_salary" => 15000],
        ];

        foreach ($externalStaff as $staff) {
            Employee::firstOrCreate(
                ["name" => $staff["name"]],
                $staff + [
                    "hire_date" => now()->toDateString(),
                    "status" => "probation",
                    "contract_type" => "fixed_term",
                ],
            );
        }
    }
}
