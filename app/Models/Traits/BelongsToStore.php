<?php

namespace App\Models\Traits;

use App\Models\Scopes\StoreScope;
use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A record that belongs to one store: queries see only the current store's rows and new
 * rows are stamped with it. A row can never be moved to another store.
 */
trait BelongsToStore
{
    protected static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function (Model $model) {
            if (!$model->store_id && ($storeId = CurrentStore::id() ?? Store::defaultId())) {
                $model->store_id = $storeId;
            }
        });

        static::updating(function (Model $model) {
            if ($model->isDirty("store_id") && $model->getOriginal("store_id") !== null) {
                throw new LogicException("A record cannot be moved to another store.");
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Every store's rows (consolidated reports for the admin only). */
    public static function acrossStores(): Builder
    {
        return static::withoutGlobalScope(StoreScope::class);
    }
}
