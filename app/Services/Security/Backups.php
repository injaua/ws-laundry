<?php

namespace App\Services\Security;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Database backups in storage/app/private/backups (not web-accessible).
 *
 * The MySQL/MariaDB database is dumped with mysqldump --single-transaction (a consistent
 * InnoDB snapshot while the app keeps working), streamed straight into a .sql.gz file.
 * The password goes through a temporary option file, never on the command line, and a
 * dump that does not reach mysqldump's "Dump completed" trailer is discarded.
 * Old files are pruned after the retention period; backups taken before the move to
 * MySQL (.sqlite.gz) stay listed and downloadable until they expire.
 */
class Backups
{
    public const DIR = "backups";

    private const FILE_PATTERN = '/^backup-\d{8}-\d{6}\.(sql|sqlite)\.gz$/';

    public static function supported(): bool
    {
        return in_array(DB::connection()->getDriverName(), ["mysql", "mariadb"], true);
    }

    /** @return array{file: string, size: int} */
    public function run(): array
    {
        if (!self::supported()) {
            throw new RuntimeException(__("app.backup_unsupported"));
        }

        $disk = Storage::disk("local");
        $disk->makeDirectory(self::DIR);
        $name = "backup-" . now()->format("Ymd-His") . ".sql.gz";
        $target = $disk->path(self::DIR . "/" . $name);
        $options = $this->writeOptionFile();

        $gz = gzopen($target, "wb6");
        $tail = "";

        try {
            // The dump is streamed into the .gz as it arrives, never held in memory.
            $result = Process::timeout((int) config("database.backup.timeout", 600))
                ->start($this->command($options))
                ->wait(function (string $type, string $output) use ($gz, &$tail) {
                    if ($type === "out") {
                        gzwrite($gz, $output);
                        $tail = substr($tail . $output, -512);
                    }
                });
        } finally {
            gzclose($gz);
            @unlink($options);
        }

        if (!$result->successful() || !str_contains($tail, "-- Dump completed")) {
            @unlink($target);

            throw new RuntimeException(trim($result->errorOutput()) ?: __("app.backup_incomplete"));
        }

        $this->prune();

        return ["file" => $name, "size" => filesize($target)];
    }

    public function list(): Collection
    {
        $disk = Storage::disk("local");

        return collect($disk->files(self::DIR))
            ->filter(fn ($f) => preg_match(self::FILE_PATTERN, basename($f)))
            ->map(fn ($f) => (object) [
                "name" => basename($f),
                "size" => $disk->size($f),
                "created_at" => Carbon::createFromTimestamp($disk->lastModified($f)),
            ])
            ->sortByDesc("created_at")->values();
    }

    public function latest(): ?object
    {
        return $this->list()->first();
    }

    public function path(string $name): ?string
    {
        // Only plain file names of our own backups (no paths).
        if (!preg_match(self::FILE_PATTERN, $name)) {
            return null;
        }
        $disk = Storage::disk("local");

        return $disk->exists(self::DIR . "/" . $name) ? $disk->path(self::DIR . "/" . $name) : null;
    }

    public function prune(): int
    {
        $limit = now()->subDays(SecuritySettings::backupRetentionDays());
        $deleted = 0;
        foreach ($this->list()->slice(1) as $backup) { // always keep the newest
            if ($backup->created_at->lt($limit)) {
                Storage::disk("local")->delete(self::DIR . "/" . $backup->name);
                $deleted++;
            }
        }

        return $deleted;
    }

    /** @return string[] */
    private function command(string $optionFile): array
    {
        return [
            config("database.backup.mysqldump", "mysqldump"),
            "--defaults-extra-file={$optionFile}", // must be the first option
            "--single-transaction",
            "--quick",
            "--routines",
            "--triggers",
            "--hex-blob",
            "--no-tablespaces",
            "--default-character-set=utf8mb4",
            DB::connection()->getDatabaseName(),
        ];
    }

    /** Connection settings for mysqldump in a private, short-lived option file. */
    private function writeOptionFile(): string
    {
        $config = DB::connection()->getConfig();
        $quote = fn ($value) => '"' . addcslashes((string) $value, "\\\"") . '"';

        $lines = ["[client]", "user=" . $quote($config["username"] ?? ""), "password=" . $quote($config["password"] ?? "")];
        if (!empty($config["unix_socket"])) {
            $lines[] = "socket=" . $quote($config["unix_socket"]);
        } else {
            $lines[] = "host=" . $quote($config["host"] ?? "127.0.0.1");
            $lines[] = "port=" . (int) ($config["port"] ?? 3306);
        }

        $disk = Storage::disk("local");
        $path = $disk->path(self::DIR . "/.mysqldump-" . Str::random(16) . ".cnf");
        file_put_contents($path, implode("\n", $lines) . "\n");
        @chmod($path, 0600);

        return $path;
    }
}
