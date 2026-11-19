<?php

namespace App\Services;

use App\Models\Account;
use App\Models\DeliveryZone;
use App\Models\ExpenseCategory;
use App\Models\MessageTemplate;
use App\Models\Package;
use App\Models\Service;
use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Gives a new store the reference data it needs to work from day one, copied from a model
 * store: the chart of accounts (PGC-NIRF), expense categories, message templates and
 * delivery zones; optionally the service / package catalogue. Business data (customers,
 * orders, stock, staff…) is never copied: every store starts empty.
 */
class StoreProvisioner
{
    public function provision(Store $store, ?Store $from = null, bool $withCatalogue = false): void
    {
        $from ??= Store::where("id", "!=", $store->id)->orderBy("id")->first();
        if (!$from) {
            return;
        }

        DB::transaction(function () use ($store, $from, $withCatalogue) {
            $accounts = CurrentStore::run($from, fn () => Account::orderBy("id")->get());
            $categories = CurrentStore::run($from, fn () => ExpenseCategory::with("account:id,code")->get());
            $templates = CurrentStore::run($from, fn () => MessageTemplate::all());
            $zones = CurrentStore::run($from, fn () => DeliveryZone::all());
            $services = $withCatalogue ? CurrentStore::run($from, fn () => Service::all()) : collect();
            $packages = $withCatalogue ? CurrentStore::run($from, fn () => Package::all()) : collect();

            CurrentStore::run($store, function () use ($store, $accounts, $categories, $templates, $zones, $services, $packages) {
                // Accounts keep their hierarchy: parents are created first (ordered by id) and
                // mapped from the old id to the new one.
                $newIds = [];
                foreach ($accounts as $account) {
                    $copy = $this->copy($account, $store);
                    $copy->parent_id = $account->parent_id ? ($newIds[$account->parent_id] ?? null) : null;
                    $copy->save();
                    $newIds[$account->id] = $copy->id;
                }
                $byCode = Account::pluck("id", "code");

                foreach ($categories as $category) {
                    $copy = $this->copy($category, $store);
                    $copy->account_id = $category->account ? ($byCode[$category->account->code] ?? null) : null;
                    $copy->save();
                }
                foreach ($templates as $template) {
                    $copy = $this->copy($template, $store);
                    $copy->created_by = null;
                    $copy->save();
                }
                foreach ([...$zones, ...$services, ...$packages] as $row) {
                    $this->copy($row, $store)->save();
                }
            });
        });
    }

    private function copy(Model $model, Store $store): Model
    {
        $copy = $model->replicate(["store_id"]);
        $copy->setRelations([]);
        $copy->store_id = $store->id;

        return $copy;
    }
}
