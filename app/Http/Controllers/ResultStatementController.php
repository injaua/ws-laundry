<?php

namespace App\Http\Controllers;

use App\Support\CurrentStore;
use App\Support\Documents\ReportDownload;
use App\Support\Documents\ReportTable;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResultStatementController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : today()->startOfYear();
        $to = $request->to ? Carbon::parse($request->to) : today();

        $rows = DB::table("accounts")
            ->join("journal_lines", "journal_lines.account_id", "=", "accounts.id")
            ->join("journal_entries", "journal_entries.id", "=", "journal_lines.journal_entry_id")
            ->where("journal_entries.store_id", CurrentStore::id())
            ->where("accounts.is_group", false)
            ->whereIn("accounts.type", ["revenue", "expense"])
            ->whereBetween("journal_entries.entry_date", [
                $from->copy()->startOfDay()->toDateTimeString(),
                $to->copy()->endOfDay()->toDateTimeString(),
            ])
            ->groupBy("accounts.code", "accounts.name", "accounts.type")
            ->orderBy("accounts.code")
            ->select(
                "accounts.code",
                "accounts.name",
                "accounts.type",
                DB::raw("SUM(journal_lines.debit) as total_debit"),
                DB::raw("SUM(journal_lines.credit) as total_credit")
            )
            ->get()
            ->map(function ($row) {
                $difference = Money::sub($row->total_debit, $row->total_credit);
                $balance = $row->type === "expense" ? $difference : -$difference;

                return (object) [
                    "code" => $row->code,
                    "name" => $row->name,
                    "type" => $row->type,
                    "group" => $this->groupOf($row->code),
                    "balance" => round($balance, 2),
                ];
            })
            ->filter(fn ($row) => abs($row->balance) > 0.004)
            ->values();

        $income = $this->sections($rows->where("type", "revenue"));
        $expenses = $this->sections($rows->where("type", "expense"));

        $totals = [
            "income" => Money::round($income->sum("subtotal")),
            "expenses" => Money::round($expenses->sum("subtotal")),
        ];
        $totals["net_result"] = Money::sub($totals["income"], $totals["expenses"]);

        if ($format = ReportDownload::requested($request)) {
            return ReportDownload::make($this->table($income, $expenses, $totals, $from, $to), $format);
        }

        return view("accounting.income-statement", compact("income", "expenses", "totals", "from", "to"));
    }

    private function table($income, $expenses, array $totals, Carbon $from, Carbon $to): ReportTable
    {
        $table = new ReportTable(__("app.income_statement"), $from->format(date_fmt()) . " – " . $to->format(date_fmt()), [
            [__("app.account_code"), "text"], [__("app.account_name"), "text"], [__("app.amount"), "money"],
        ], __("app.income_statement") . " " . $from->format("Y-m-d") . " " . $to->format("Y-m-d"));

        foreach ([[__("app.income_and_gains"), $income, __("app.total_income"), $totals["income"]],
            [__("app.expenses_and_losses"), $expenses, __("app.total_expenses_and_losses"), $totals["expenses"]]] as [$title, $sections, $totalLabel, $total]) {
            $table->section($title);
            foreach ($sections as $group) {
                $table->row([$group->code, $group->label, $group->subtotal], "subtotal");
                foreach ($group->rows as $row) {
                    $table->row([$row->code, $row->name, $row->balance], "indent");
                }
            }
            $table->row(["", $totalLabel, $total], "subtotal");
        }

        return $table->row(["", __("app.net_result"), $totals["net_result"]], "total");
    }

    /**
     * Group leaf accounts by their PGC subsection (first two code segments).
     */
    private function sections($rows)
    {
        return $rows->groupBy("group")
            ->sortKeys()
            ->map(function ($groupRows, $group) {
                $key = "app.income_group_" . str_replace(".", "_", $group);
                $label = __($key);

                return (object) [
                    "code" => $group,
                    "label" => $label === $key ? $group : $label,
                    "rows" => $groupRows->values(),
                    "subtotal" => Money::round($groupRows->sum("balance")),
                ];
            })
            ->values();
    }

    private function groupOf(string $code): string
    {
        $segments = explode(".", $code);

        return count($segments) >= 2 ? $segments[0] . "." . $segments[1] : $code;
    }
}
