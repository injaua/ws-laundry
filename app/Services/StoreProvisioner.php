<?php

namespace App\Services;

use App\Models\Account;
use App\Models\DeliveryZone;
use App\Models\ExpenseCategory;
use App\Models\MessageTemplate;
use App\Models\Package;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Store;
use App\Services\WhatsApp\AutomaticNotifications;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Gives a new store the reference data it needs to work from day one, copied from a model
 * store: the chart of accounts (PGC-NIRF), expense categories, message templates (and which
 * template each automatic notification uses) and delivery zones; optionally the service /
 * package catalogue. Business data (customers, orders, stock, staff…) is never copied: every
 * store starts empty. WhatsApp and payment settings are not copied: the new store uses the
 * company's until it is given its own.
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
            $notifications = CurrentStore::run($from, fn () => $this->notificationSettings());
            $zones = CurrentStore::run($from, fn () => DeliveryZone::all());
            $services = $withCatalogue ? CurrentStore::run($from, fn () => Service::all()) : collect();
            $packages = $withCatalogue ? CurrentStore::run($from, fn () => Package::all()) : collect();

            CurrentStore::run($store, function () use ($store, $accounts, $categories, $templates, $notifications, $zones, $services, $packages) {
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
                $templateIds = [];
                foreach ($templates as $template) {
                    $copy = $this->copy($template, $store);
                    $copy->created_by = null;
                    $copy->save();
                    $templateIds[$template->id] = $copy->id;
                }

                // Same automatic notifications as the model store, pointing at the copied templates.
                foreach ($notifications as $key => $value) {
                    if (str_ends_with($key, "_template_id")) {
                        $value = $templateIds[(int) $value] ?? null;
                    }
                    SiteSetting::setAt($store->id, $key, $value);
                }
                foreach ([...$zones, ...$services, ...$packages] as $row) {
                    $this->copy($row, $store)->save();
                }
            });
        });
    }

    /** @return array<string, mixed> the automatic notification settings as seen from the store worked in */
    private function notificationSettings(): array
    {
        $keys = [];
        foreach (array_keys(AutomaticNotifications::EVENTS) as $event) {
            array_push($keys, "notify_{$event}_enabled", "notify_{$event}_template_id", "notify_{$event}_days");
        }
        $values = SiteSetting::many($keys);

        // An inherited choice may point at another store's template: keep the model store's own one.
        $notifications = app(AutomaticNotifications::class);
        foreach (array_keys(AutomaticNotifications::EVENTS) as $event) {
            if (array_key_exists("notify_{$event}_template_id", $values)) {
                $values["notify_{$event}_template_id"] = $notifications->chosenTemplate($event)?->id;
            }
        }

        return $values;
    }

    private function copy(Model $model, Store $store): Model
    {
        $copy = $model->replicate(["store_id"]);
        $copy->setRelations([]);
        $copy->store_id = $store->id;

        return $copy;
    }
}
