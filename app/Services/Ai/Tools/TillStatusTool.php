<?php

namespace App\Services\Ai\Tools;

use App\Models\CashSession;
use App\Models\User;

class TillStatusTool extends AiTool
{
    public function name(): string
    {
        return "till_status";
    }

    public function description(): string
    {
        return "Till shifts: the open ones with the cash expected in the drawer, and the recent closed ones with their count differences "
            . "(negative = shortage). A cashier only sees their own shifts.";
    }

    public function ability(): ?string
    {
        return "view-records";
    }

    protected function properties(): array
    {
        return [...self::periodProperties(), ...self::limitProperty()];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        [$from, $to] = self::period($input, now()->toImmutable()->subDays(30));
        $own = fn ($q) => $user->isCashier() ? $q->where("user_id", $user->id) : $q;

        $open = $own(CashSession::open()->with("user:id,name"))->get();
        $closed = $own(CashSession::where("status", CashSession::STATUS_CLOSED)->with(["user:id,name", "counts"]))
            ->whereBetween("closed_at", [$from, $to])->latest("closed_at")->limit(self::limit($input))->get();

        return [
            "open" => $open->map(fn (CashSession $s) => [
                "cashier" => $s->user?->name,
                "opened_at" => $s->opened_at?->format("Y-m-d H:i"),
                "expected_cash" => money($s->expectedCash()),
            ])->all(),
            "closed" => $closed->map(fn (CashSession $s) => [
                "cashier" => $s->user?->name,
                "closed_at" => $s->closed_at?->format("Y-m-d H:i"),
                "difference" => money($s->totalDifference()),
                "note" => $s->closing_note,
                "link" => route("cash-sessions.show", $s),
            ])->all(),
            "shortages_total" => money($closed->sum(fn (CashSession $s) => min(0, $s->totalDifference()))),
        ];
    }
}
