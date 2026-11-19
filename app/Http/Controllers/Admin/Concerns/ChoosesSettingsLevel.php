<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Store;
use App\Support\CurrentStore;
use App\Support\StoreSettings;
use Illuminate\Http\Request;

/**
 * Settings pages for a group a store can configure itself (StoreSettings): the admin edits
 * either the company configuration (?level=company) or that of the store worked in
 * (?level=store). Without a choice, a page shows the store's own configuration when it has
 * one, else the company's; a form without a choice saves the company's.
 *
 * The store owner only ever sees and edits their own store: the first save starts the store's
 * own configuration empty (the company credentials are never copied into it).
 */
trait ChoosesSettingsLevel
{
    /** @return int|null the store id, or null for the company */
    protected function settingsLevel(Request $request, string $group, bool $forSaving = false): ?int
    {
        $storeId = CurrentStore::id();

        if (!$request->user()?->isAdmin()) {
            abort_unless($storeId, 403);
            if ($forSaving) {
                StoreSettings::takeOver($group, $storeId, copyCompany: false);
            }

            return $storeId;
        }

        $chosen = $request->input("level");

        if ($chosen === "store") {
            abort_unless($storeId, 404);

            return $storeId;
        }
        if ($chosen === "company" || $forSaving) {
            return null;
        }

        return StoreSettings::levelFor($group, $storeId);
    }

    /** Query string that reopens the page on a level (none when it is what the page shows anyway). */
    protected function levelQuery(?int $level, string $group): array
    {
        if (!request()->user()?->isAdmin()) {
            return [];
        }
        $default = StoreSettings::levelFor($group, CurrentStore::id());

        return $level === $default ? [] : ["level" => $level === null ? "company" : "store"];
    }

    /** What the level bar of the page shows (resources/views/admin/partials/settings-level.blade.php). */
    protected function levelBar(string $group, ?int $level, string $route): array
    {
        $owners = StoreSettings::owners($group);

        return [
            "group" => $group,
            "route" => $route,
            "level" => $level,
            "store" => CurrentStore::store(),
            "owns" => StoreSettings::owns($group, CurrentStore::id()),
            "ownStores" => $owners ? Store::whereKey($owners)->orderBy("name")->get() : collect(),
        ];
    }
}
