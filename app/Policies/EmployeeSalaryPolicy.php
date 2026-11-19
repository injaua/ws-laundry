<?php

namespace App\Policies;

use App\Models\EmployeeSalary;
use App\Models\User;
use App\Policies\Concerns\OwnsStoreRecords;

/**
 * Payslips: an accountant edits, pays, reverses or deletes only the payslips they
 * prepared. The lock on paid payslips stays in the controller.
 */
class EmployeeSalaryPolicy
{
    use OwnsStoreRecords;

    private const ROLES = ["admin", "owner", "accountant"];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(self::ROLES);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::ROLES);
    }

    public function update(User $user, EmployeeSalary $salary): bool
    {
        return $this->managesOrOwns($user, $salary, "created_by", self::ROLES);
    }

    public function delete(User $user, EmployeeSalary $salary): bool
    {
        return $this->update($user, $salary);
    }

    public function pay(User $user, EmployeeSalary $salary): bool
    {
        return $this->update($user, $salary);
    }
}
