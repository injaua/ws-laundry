<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-record rule shared by the financial policies: inside the store in use,
 * admin and owner manage every record; any other role changes only the records
 * it registered itself. Records without a known author (older rows, automatic
 * postings) are left to management.
 */
trait OwnsStoreRecords
{
    /** Roles that manage every record of their store. */
    protected const MANAGERS = ["admin", "owner"];

    protected function isManager(User $user): bool
    {
        return $user->hasRole(self::MANAGERS);
    }

    /**
     * The global store scope already answers 404 for another store's rows; this
     * keeps the rule when a record is reached some other way (queues, relations…).
     */
    protected function inCurrentStore(Model $record): bool
    {
        $storeId = CurrentStore::id();

        return $storeId === null || $record->store_id === null || (int) $record->store_id === (int) $storeId;
    }

    protected function isAuthor(User $user, Model $record, string $column): bool
    {
        return $record->{$column} !== null && (int) $record->{$column} === (int) $user->id;
    }

    /** Manager of the store, or one of $roles who registered the record. */
    protected function managesOrOwns(User $user, Model $record, string $column, array $roles): bool
    {
        if (!$this->inCurrentStore($record)) {
            return false;
        }

        return $this->isManager($user) || ($user->hasRole($roles) && $this->isAuthor($user, $record, $column));
    }
}
