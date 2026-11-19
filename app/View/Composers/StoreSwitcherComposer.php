<?php

namespace App\View\Composers;

use App\Support\CurrentStore;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Top-bar store indicator: admins get a switcher listing every active store; anyone else
 * sees the name of the store they work in.
 */
class StoreSwitcherComposer
{
    private ?array $data = null;

    public function compose(View $view): void
    {
        $view->with($this->data ??= $this->data());
    }

    private function data(): array
    {
        $user = auth()->user();
        if (!$user) {
            return ["currentStore" => null, "switchableStores" => new Collection];
        }

        return [
            "currentStore" => CurrentStore::store(),
            "switchableStores" => $user->isAdmin() ? CurrentStore::availableTo($user) : new Collection,
        ];
    }
}
