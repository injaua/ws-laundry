<?php

namespace App\Models\Scopes;

use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Limits every query of a store-owned model to the current store. */
class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = CurrentStore::id();
        if ($storeId !== null) {
            $builder->where($model->qualifyColumn("store_id"), $storeId);
        }
    }
}
