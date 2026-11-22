<?php

namespace App\Support;

use App\Models\Store;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * The store the current request (or job) works in.
 *
 *  - a user who belongs to a store (owner, accountant, cashier, courier) always works in it;
 *  - an admin works in the store chosen in the top-bar switcher (session), by default the
 *    first active store;
 *  - jobs, commands and public pages run without a store unless one is set with run().
 *
 * Every model using BelongsToStore is filtered by this id, so a request never reads or
 * writes another store's records.
 */
final class CurrentStore
{
    public const SESSION_KEY = "current_store_id";

    /** RequestCache keys: the stack of explicit stores (run()) and the stores already looked up. */
    private const STACK = "current-store:stack";

    private const FOUND = "current-store:found:";

    public static function id(): ?int
    {
        $stack = RequestCache::instance()->get(self::STACK, []);
        if ($stack !== []) {
            return end($stack);
        }

        try {
            $user = Auth::user();
        } catch (Throwable) {
            return null; // no auth guard (console boot)
        }
        if (!$user) {
            return null;
        }
        if ($user->store_id) {
            return (int) $user->store_id;
        }
        if ($user->isAdmin()) {
            return self::adminStoreId();
        }

        return null;
    }

    public static function store(): ?Store
    {
        $id = self::id();

        return $id ? self::find($id) : null;
    }

    /** Run $callback with $store as the current store (jobs, commands, consolidated reports). */
    public static function run(Store|int|null $store, Closure $callback): mixed
    {
        $cache = RequestCache::instance();
        $stack = $cache->get(self::STACK, []);
        $stack[] = $store instanceof Store ? $store->id : $store;
        $cache->put(self::STACK, $stack);
        try {
            return $callback();
        } finally {
            $stack = $cache->get(self::STACK, []);
            array_pop($stack);
            $cache->put(self::STACK, $stack);
        }
    }

    /**
     * Run $callback once per active store, inside that store (scheduled commands).
     *
     * @return array<int, mixed> results keyed by store id
     */
    public static function each(Closure $callback): array
    {
        $results = [];
        foreach (Store::active()->orderBy("id")->get() as $store) {
            $results[$store->id] = self::run($store, fn () => $callback($store));
        }

        return $results;
    }

    /** Admin: switch the store worked in. */
    public static function switchTo(Store $store): void
    {
        session([self::SESSION_KEY => $store->id]);
    }

    /** Stores the user can work in: all active stores for an admin, their own for anyone else. */
    public static function availableTo($user): Collection
    {
        if ($user->isAdmin()) {
            return Store::active()->orderBy("name")->get();
        }

        return Store::whereKey($user->store_id)->get();
    }

    private static function find(int $id): ?Store
    {
        return RequestCache::instance()->remember(self::FOUND . $id, fn () => Store::find($id));
    }

    /** Forgets the explicit stores and the stores looked up (start of a request / test). */
    public static function flush(): void
    {
        RequestCache::instance()->forget("current-store:");
        Store::forgetDefault();
    }

    private static function adminStoreId(): ?int
    {
        $chosen = (int) session(self::SESSION_KEY);
        if ($chosen && ($store = self::find($chosen)) && $store->is_active) {
            return $store->id;
        }

        return Store::active()->orderBy("id")->value("id") ?? Store::defaultId();
    }
}
