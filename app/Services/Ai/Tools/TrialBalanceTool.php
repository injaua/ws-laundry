<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\TrialBalanceService;
use Illuminate\Support\Carbon;

class TrialBalanceTool extends AiTool
{
    public function name(): string
    {
        return "trial_balance";
    }

    public function description(): string
    {
        return "Trial balance (PGC-NIRF) of a period: per account code, name, type, total debit, total credit and balance. "
            . "Optional account code prefix (e.g. '4.4' for taxes, '6' for costs, '7' for income).";
    }

    public function ability(): ?string
    {
        return "manage-finance";
    }

    protected function properties(): array
    {
        return [...self::periodProperties(), "account_prefix" => ["type" => "string", "description" => "Account code prefix"]];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        [$from, $to] = self::period($input);
        $service = app(TrialBalanceService::class);
        $rows = $service->rows(Carbon::parse($from), Carbon::parse($to));
        $prefix = self::text($input, "account_prefix", 20);
        if ($prefix !== "") {
            $rows = $rows->filter(fn ($r) => str_starts_with((string) $r->code, $prefix))->values();
        }
        $totals = $service->totals($rows);

        return [
            "period" => ["from" => $from->toDateString(), "to" => $to->toDateString()],
            "accounts" => $rows->take(80)->map(fn ($r) => [
                "code" => $r->code,
                "name" => $r->name,
                "type" => $r->type,
                "debit" => money($r->total_debit),
                "credit" => money($r->total_credit),
                "balance" => money($r->balance_debit > 0 ? $r->balance_debit : -$r->balance_credit),
            ])->all(),
            "totals" => collect($totals)->map(fn ($v) => money($v))->all(),
            "link" => route("accounting.trial-balance"),
        ];
    }
}
