<?php

namespace Tests\Feature;

use App\Services\Security\Backups;
use App\Services\Security\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * MySQL backups: mysqldump streamed into a .sql.gz, password kept off the command line,
 * incomplete dumps discarded.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake("local");
    }

    public function test_mysql_is_supported(): void
    {
        $this->assertTrue(Backups::supported());
    }

    public function test_backup_streams_the_dump_into_a_gzip_file(): void
    {
        $command = null;
        Process::fake(function (PendingProcess $process) use (&$command) {
            $command = $process->command;

            return Process::describe()
                ->output("-- MySQL dump\n")
                ->output("CREATE TABLE `orders` (`id` bigint);\n")
                ->output("-- Dump completed on 2026-11-19 10:00:00\n");
        });

        $result = app(Backups::class)->run();

        $this->assertMatchesRegularExpression('/^backup-\d{8}-\d{6}\.sql\.gz$/', $result["file"]);
        $dump = gzdecode(Storage::disk("local")->get(Backups::DIR . "/" . $result["file"]));
        $this->assertStringContainsString("CREATE TABLE `orders`", $dump);
        $this->assertStringContainsString("-- Dump completed", $dump);

        // Consistent snapshot, credentials through an option file, never on the command line.
        $this->assertContains("--single-transaction", $command);
        $this->assertStringStartsWith("--defaults-extra-file=", $command[1]);
        $this->assertStringNotContainsString((string) config("database.connections.mysql.password") ?: "\0", implode(" ", $command));
        $this->assertSame(config("database.connections.mysql.database"), end($command));

        // The option file is gone once the dump is done.
        $this->assertSame([], array_values(array_filter(
            Storage::disk("local")->allFiles(Backups::DIR),
            fn ($f) => str_ends_with($f, ".cnf"),
        )));
    }

    public function test_an_incomplete_dump_is_discarded(): void
    {
        Process::fake(fn () => Process::describe()->output("CREATE TABLE `orders` (`id` bigint);\n"));

        try {
            app(Backups::class)->run();
            $this->fail("An incomplete dump must not be kept.");
        } catch (RuntimeException $e) {
            $this->assertSame(__("app.backup_incomplete"), $e->getMessage());
        }

        $this->assertSame([], app(Backups::class)->list()->all());
    }

    public function test_a_failing_mysqldump_reports_its_error(): void
    {
        Process::fake(fn () => Process::describe()->errorOutput("mysqldump: Got error: 1045: Access denied")->exitCode(2));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Access denied");

        app(Backups::class)->run();
    }

    public function test_old_sqlite_backups_stay_listed_and_downloadable(): void
    {
        Storage::disk("local")->put(Backups::DIR . "/backup-20261118-224704.sqlite.gz", "old");
        Storage::disk("local")->put(Backups::DIR . "/notes.txt", "not a backup");

        $backups = app(Backups::class);

        $this->assertSame(["backup-20261118-224704.sqlite.gz"], $backups->list()->pluck("name")->all());
        $this->assertNotNull($backups->path("backup-20261118-224704.sqlite.gz"));
        $this->assertNull($backups->path("notes.txt"));
        $this->assertNull($backups->path("../.env"));
    }

    public function test_pruning_keeps_the_newest_backup(): void
    {
        SecuritySettings::save(["two_factor_roles" => [], "idle_minutes" => 60, "backup_retention_days" => 7, "fiscal_retention_years" => 10]);
        $disk = Storage::disk("local");
        foreach (["backup-20260101-010000.sql.gz", "backup-20260102-010000.sql.gz"] as $i => $name) {
            $disk->put(Backups::DIR . "/" . $name, "x");
            touch($disk->path(Backups::DIR . "/" . $name), now()->subDays(30 - $i)->getTimestamp());
        }

        $this->assertSame(1, app(Backups::class)->prune());
        $this->assertSame(["backup-20260102-010000.sql.gz"], app(Backups::class)->list()->pluck("name")->all());
    }
}
