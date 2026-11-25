<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\Ai\Insights\InsightGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateAiInsights extends Command
{
    protected $signature = "ai:insights {--weekly : Also run the weekly detectors (they run on Mondays anyway)} {--store= : Only this store id}";

    protected $description = "Create the automatic insights: store alerts, daily briefings with the forecast, and the company briefing";

    public function handle(InsightGenerator $generator): int
    {
        $store = $this->option("store") ? Store::find((int) $this->option("store")) : null;
        if ($this->option("store") && !$store) {
            $this->error("Store not found.");

            return self::FAILURE;
        }

        $result = $generator->run(CarbonImmutable::today(), (bool) $this->option("weekly"), $store);
        $this->info("{$result["insights"]} insight(s) created for {$result["stores"]} store(s).");

        return self::SUCCESS;
    }
}
