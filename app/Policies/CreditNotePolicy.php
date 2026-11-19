<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;
use App\Policies\Concerns\OwnsStoreRecords;

/**
 * Credit notes give money back or forgive debt, so only management issues, voids
 * or refunds them. Cashiers may see and print them to hand to the customer.
 */
class CreditNotePolicy
{
    use OwnsStoreRecords;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(["admin", "owner", "accountant"]);
    }

    public function view(User $user, CreditNote $note): bool
    {
        return $user->hasRole(["admin", "owner", "cashier", "accountant"]) && $this->inCurrentStore($note);
    }

    public function create(User $user): bool
    {
        return $this->isManager($user);
    }

    public function void(User $user, CreditNote $note): bool
    {
        return $this->isManager($user) && $this->inCurrentStore($note) && !$note->isVoided();
    }

    /** Pay a refund note out to the customer's mobile wallet (state checks live in MobilePayments). */
    public function refund(User $user, CreditNote $note): bool
    {
        return $this->isManager($user) && $this->inCurrentStore($note);
    }
}
