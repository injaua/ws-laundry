<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;
use App\Policies\Concerns\OwnsStoreRecords;

/**
 * Manual journal entries: an accountant changes or deletes only the entries they
 * posted. Automatic entries (payments, expenses…) and closed periods are locked
 * in the controller for every role.
 */
class JournalEntryPolicy
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

    public function update(User $user, JournalEntry $entry): bool
    {
        return $this->managesOrOwns($user, $entry, "posted_by", self::ROLES);
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return $this->update($user, $entry);
    }
}
