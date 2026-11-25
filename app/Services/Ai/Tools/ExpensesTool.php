<?php

namespace App\Services\Ai\Tools;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;

class ExpensesTool extends AiTool
{
    public function name(): string
    {
        return "expenses";
    }

    public function description(): string
    {
        return "Expenses of a period: total, per category (with the previous period of the same length) and the largest ones.";
    }

    public function ability(): ?string
    {
        return "manage-finance";
    }

    protected function properties(): array
    {
        return [...self::periodProperties(), ...self::limitProperty()];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        [$from, $to] = self::period($input);
        $days = (int) $from->diffInDays($to->startOfDay()) + 1;
        $prevFrom = $from->subDays($days);
        $prevTo = $from->subDay()->endOfDay();

        $names = ExpenseCategory::pluck("name", "key");
        $byCategory = fn ($a, $b) => Expense::whereBetween("expense_date", [$a->toDateString(), $b->toDateString()])
            ->selectRaw("category, SUM(amount) as total")->groupBy("category")->pluck("total", "category");
        $now = $byCategory($from, $to);
        $before = $byCategory($prevFrom, $prevTo);

        $largest = Expense::whereBetween("expense_date", [$from->toDateString(), $to->toDateString()])
            ->orderByDesc("amount")->limit(self::limit($input))->get();

        return [
            "period" => ["from" => $from->toDateString(), "to" => $to->toDateString()],
            "total" => money($now->sum()),
            "previous_period_total" => money($before->sum()),
            "by_category" => $now->keys()->merge($before->keys())->unique()->map(fn ($key) => [
                "category" => $names[$key] ?? $key,
                "total" => money((float) ($now[$key] ?? 0)),
                "previous_period" => money((float) ($before[$key] ?? 0)),
            ])->values()->all(),
            "largest" => $largest->map(fn (Expense $e) => [
                "title" => $e->title,
                "category" => $names[$e->category] ?? $e->category,
                "amount" => money($e->amount),
                "date" => self::date($e->expense_date),
            ])->all(),
            "link" => route("expenses.index"),
        ];
    }
}
