<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * "exists" and "unique" validation rules on store-owned tables only look at the current
 * store: a form can never point at another store's customer, item or employee, and codes
 * (customer phone, account code…) are unique per store.
 */
class StorePresenceVerifier extends DatabasePresenceVerifier
{
    protected function table($table)
    {
        $query = parent::table($table);

        if (in_array($table, Store::SCOPED_TABLES, true) && ($storeId = CurrentStore::id())) {
            $query->where("store_id", $storeId);
        }

        return $query;
    }
}
