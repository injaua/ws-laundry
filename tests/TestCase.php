<?php

namespace Tests;

use App\Models\CashSession;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase empties the database: refuse to run on anything but a test database
     * (phpunit.xml points DB_DATABASE at washly-lavandaria-testing). The database is built from
     * database/schema/mysql-schema.sql (php artisan schema:snapshot), not migration by migration.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app["config"]->get("database.default");
        $database = (string) $app["config"]->get("database.connections.{$connection}.database");
        // "--parallel" gives each process its own copy: washly-lavandaria-testing_test_1, _2…
        if (!preg_match('/[-_]testing(_test_\d+)?$/', $database)) {
            throw new RuntimeException("Tests must run on a test database (name ending in -testing), not on \"{$database}\".");
        }

        return $app;
    }

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
