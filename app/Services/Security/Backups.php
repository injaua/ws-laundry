<?php

namespace App\Services\Security;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Database backups in storage/app/private/backups (not web-accessible). SQLite is copied
 * with VACUUM INTO (a consistent snapshot even while the app is in use) and gzipped.
 * Old files are pruned after the retention period.
 */
class Backups
{
    public const DIR = "backups";

    public static function supported(): bool
    {
        return DB::connection()->getDriverName() === "sqlite" && config("database.connections.sqlite.database") !== ":memory:";
    }

    /** @return array{file: string, size: int} */
    public function run(): array
    {
        if (!self::supported()) {
            throw new RuntimeException(__("app.backup_unsupported"));
        }

        $disk = Storage::disk("local");
        $disk->makeDirectory(self::DIR);
        $name = "backup-" . now()->format("Ymd-His") . ".sqlite";
        $snapshot = $disk->path(self::DIR . "/" . $name);

        DB::statement("VACUUM INTO ?", [$snapshot]);

        $gz = gzopen($snapshot . ".gz", "wb6");
        $in = fopen($snapshot, "rb");
        while (!feof($in)) {
            gzwrite($gz, fread($in, 1024 * 512));
        }
        fclose($in);
        gzclose($gz);
        unlink($snapshot);

        $this->prune();

        return ["file" => $name . ".gz", "size" => filesize($snapshot . ".gz")];
    }

    public function list(): Collection
    {
        $disk = Storage::disk("local");

        return collect($disk->files(self::DIR))
            ->filter(fn ($f) => str_ends_with($f, ".sqlite.gz"))
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
        if (!preg_match('/^backup-\d{8}-\d{6}\.sqlite\.gz$/', $name)) {
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
}
