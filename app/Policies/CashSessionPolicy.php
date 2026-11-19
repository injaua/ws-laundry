<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;

/**
 * Operators work their own till; admin and owner review every shift and may close
 * a shift an operator left open.
 */
class CashSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(["admin", "owner", "cashier", "accountant"]);
    }

    public function view(User $user, CashSession $session): bool
    {
        return $this->isManager($user) || $user->isAccountant() || $session->user_id === $user->id;
    }

    public function open(User $user): bool
    {
        return $user->hasRole(["admin", "owner", "cashier"]);
    }

    /** Withdrawals and deposits are made only by the operator holding the till. */
    public function move(User $user, CashSession $session): bool
    {
        return $session->isOpen() && $session->user_id === $user->id;
    }

    public function close(User $user, CashSession $session): bool
    {
        return $session->isOpen() && ($session->user_id === $user->id || $this->isManager($user));
    }

    /** Expected amounts stay hidden from operators until their shift is closed. */
    public function seeExpected(User $user, CashSession $session): bool
    {
        return $this->isManager($user) || $user->isAccountant() || !$session->isOpen();
    }

    private function isManager(User $user): bool
    {
        return $user->hasRole(["admin", "owner"]);
    }
}
