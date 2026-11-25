<?php

namespace App\Services\Ai\Tools;

use App\Models\Complaint;
use App\Models\User;

class ComplaintsTool extends AiTool
{
    public function name(): string
    {
        return "complaints";
    }

    public function description(): string
    {
        return "Customer complaints: the pending ones (open / in progress, with their deadline) and counts per category and status in a period.";
    }

    public function ability(): ?string
    {
        return "work-counter";
    }

    protected function properties(): array
    {
        return [...self::periodProperties(), ...self::limitProperty()];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        [$from, $to] = self::period($input);
        $inPeriod = fn () => Complaint::whereBetween("created_at", [$from, $to]);

        return [
            "period" => ["from" => $from->toDateString(), "to" => $to->toDateString()],
            "by_category" => $inPeriod()->selectRaw("category, COUNT(*) as n")->groupBy("category")->pluck("n", "category")->all(),
            "by_status" => $inPeriod()->selectRaw("status, COUNT(*) as n")->groupBy("status")->pluck("n", "status")->all(),
            "pending" => Complaint::pending()->with(["customer:id,name", "order:id,order_number"])->orderBy("due_at")
                ->limit(self::limit($input))->get()->map(fn (Complaint $c) => [
                    "number" => $c->number,
                    "customer" => $c->customer?->name,
                    "order" => $c->order?->order_number,
                    "category" => $c->category,
                    "status" => $c->status,
                    "due" => self::date($c->due_at),
                    "overdue" => $c->due_at !== null && $c->due_at->lt(today()),
                    "link" => route("complaints.show", $c),
                ])->all(),
        ];
    }
}
