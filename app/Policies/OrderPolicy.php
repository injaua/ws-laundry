<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Who may do what with an order. Route middleware keeps the coarse role gate;
 * this policy is the per-record rule the controllers and views rely on.
 */
class OrderPolicy
{
    private const STAFF = ["admin", "owner", "cashier"];

    private const MANAGERS = ["admin", "owner"];

    /** The accountant consults orders and their documents, but does not change them. */
    private const READERS = ["admin", "owner", "cashier", "accountant"];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(self::READERS);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasRole(self::READERS);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::STAFF);
    }

    /**
     * Cashiers may only advance the status; that field-level rule and the lock on
     * completed orders live in UpdateOrder, which reports them as friendly errors.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->hasRole(self::STAFF);
    }

    /** Editing order data (customer, dates, notes) is a management task. */
    public function edit(User $user, Order $order): bool
    {
        return $user->hasRole(self::MANAGERS);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasRole(self::MANAGERS);
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->hasRole(self::MANAGERS);
    }
}
