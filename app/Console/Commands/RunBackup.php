<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\Security\Backups;
use Illuminate\Console\Command;
use Throwable;

class RunBackup extends Command
{
    protected $signature = "backup:run";

    protected $description = "Database backup to storage/app/private/backups (old copies are pruned)";

    public function handle(Backups $backups): int
    {
        try {
            $result = $backups->run();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        ActivityLog::security("backup_created", null, "Backup created: {$result["file"]}");
        $this->info("Backup: {$result["file"]} (" . number_format($result["size"] / 1024, 0) . " KB)");

        return self::SUCCESS;
    }
}
