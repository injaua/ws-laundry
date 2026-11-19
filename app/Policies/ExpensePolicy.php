<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\OwnsStoreRecords;

/**
 * Expenses: admin, owner and accountant record and consult them; an accountant
 * changes or deletes only the expenses they recorded. The closed-period lock
 * stays in the controller, which reports it as a friendly error.
 */
class ExpensePolicy
{
    use OwnsStoreRecords;

    private const ROLES = ["admin", "owner", "accountant"];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(self::ROLES);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasRole(self::ROLES) && $this->inCurrentStore($expense);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::ROLES);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->managesOrOwns($user, $expense, "user_id", self::ROLES);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }
}
