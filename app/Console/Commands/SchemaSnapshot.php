<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Writes database/schema/mysql-schema.sql: the structure of every table plus the rows the
 * migrations insert (chart of accounts, currencies, languages, templates, main store…).
 *
 * "migrate" on an empty database (tests, new installs) loads that one file instead of running
 * the ~90 migrations one by one, then runs only the migrations created after it. Laravel's own
 * schema:dump keeps the structure only, and the rows the migrations seed would be lost.
 *
 * Run it after adding migrations (it rebuilds a scratch database, never the one in use):
 *     php artisan schema:snapshot
 */
class SchemaSnapshot extends Command
{
    protected $signature = "schema:snapshot {--database=washly-lavandaria-testing : Scratch database, emptied and rebuilt (must end in -testing)}";

    protected $description = "Dump the schema and the rows seeded by migrations, for fast fresh installs and tests";

    /** Tables whose rows are runtime state, never part of the snapshot. */
    private const SKIP = ["migrations", "cache", "cache_locks", "sessions", "jobs", "job_batches", "failed_jobs"];

    public function handle(): int
    {
        $database = (string) $this->option("database");
        if (!preg_match('/[-_]testing$/', $database)) {
            $this->error("The scratch database must end in -testing: it is emptied.");

            return self::FAILURE;
        }

        $connection = "mysql";
        config(["database.default" => $connection, "database.connections.{$connection}.database" => $database]);
        DB::purge($connection);

        $path = database_path("schema/{$connection}-schema.sql");
        File::delete($path); // otherwise migrate:fresh would load the old snapshot

        $this->components->task("Running every migration on {$database}", fn () => $this->callSilently("migrate:fresh", ["--force" => true]) === 0);
        $this->components->task("Dumping the structure", fn () => $this->callSilently("schema:dump", ["--database" => $connection]) === 0);

        $tables = collect(DB::select("select table_name as name from information_schema.tables where table_schema = database() and table_type = 'BASE TABLE'"))
            ->pluck("name")
            ->reject(fn ($table) => in_array($table, self::SKIP, true))
            ->filter(fn ($table) => DB::table($table)->exists())
            ->values()->all();

        if ($tables !== []) {
            $config = config("database.connections.{$connection}");
            $result = Process::env(["MYSQL_PWD" => (string) $config["password"]])->timeout(300)->run(array_merge([
                "mysqldump",
                "--host=" . $config["host"], "--port=" . $config["port"], "--user=" . $config["username"],
                "--no-create-info", "--skip-triggers", "--complete-insert", "--skip-comments",
                "--skip-lock-tables", "--skip-add-locks", "--hex-blob", "--tz-utc",
                $database,
            ], $tables));
            if (!$result->successful()) {
                $this->error(trim($result->errorOutput()));

                return self::FAILURE;
            }
            File::append($path, "\n-- Rows inserted by the migrations (schema:snapshot)\n" . $result->output());
        }

        $this->components->info("Snapshot written to database/schema/{$connection}-schema.sql (" . count($tables) . " tables with rows: " . implode(", ", $tables) . ").");

        return self::SUCCESS;
    }
}
