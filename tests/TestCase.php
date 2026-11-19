<?php

namespace Tests;

use App\Models\CashSession;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests check the HTML, not the compiled assets: no `npm run build` needed.
        // tests/Unit/ViewsHaveNoInlineAssetsTest checks every @vite entry exists.
        $this->withoutVite();

        // Static per-request state (current store, default store id) must not leak between tests.
        CurrentStore::flush();
    }

    /** Cashiers can only take payments with an open till shift. */
    protected function openTill(User $user, float $float = 0): CashSession
    {
        return CashSession::create([
            "user_id" => $user->id,
            "status" => CashSession::STATUS_OPEN,
            "opened_at" => now(),
            "opening_float" => $float,
        ]);
    }
}
