<?php

namespace App\Policies;

use App\Models\User;

/**
 * Admins manage every account in every store; an owner manages the staff (accountants,
 * cashiers, couriers) of their own store.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(["admin", "owner"]);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(["admin", "owner"]);
    }

    public function update(User $actor, User $user): bool
    {
        return $user->manageableBy($actor);
    }

    public function delete(User $actor, User $user): bool
    {
        // Self-deletion and the last-admin guard are reported as messages by the controller.
        return $user->manageableBy($actor);
    }

    public function toggleActive(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    /** Roles this actor may assign when creating or editing an account. */
    public static function assignableRoles(User $actor): array
    {
        return $actor->isAdmin() ? ["admin", "owner", "accountant", "cashier", "courier"] : ["accountant", "cashier", "courier"];
    }
}
