<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;

/**
 * Credit notes give money back or forgive debt, so only management issues or voids
 * them. Cashiers may see and print them to hand to the customer.
 */
class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(["admin", "owner", "accountant"]);
    }

    public function view(User $user, CreditNote $note): bool
    {
        return $user->hasRole(["admin", "owner", "cashier", "accountant"]);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(["admin", "owner"]);
    }

    public function void(User $user, CreditNote $note): bool
    {
        return $user->hasRole(["admin", "owner"]) && !$note->isVoided();
    }
}
