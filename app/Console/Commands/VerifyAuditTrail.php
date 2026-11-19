<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class VerifyAuditTrail extends Command
{
    protected $signature = "audit:verify";

    protected $description = "Checks that no audit-trail entry was changed or removed in the database";

    public function handle(): int
    {
        $result = ActivityLog::verifyChain();

        if ($result["ok"]) {
            $this->info("Audit trail intact: {$result["checked"]} entries checked.");

            return self::SUCCESS;
        }

        $this->error("Audit trail broken at entry #{$result["broken_id"]}: it, or an earlier one, was changed or removed.");

        return self::FAILURE;
    }
}
