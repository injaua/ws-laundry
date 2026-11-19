<?php

namespace App\Http\Controllers;

use App\Support\CurrentStore;
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
                $difference = (float) $row->total_debit - (float) $row->total_credit;
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
            "income" => round((float) $income->sum("subtotal"), 2),
            "expenses" => round((float) $expenses->sum("subtotal"), 2),
        ];
        $totals["net_result"] = round($totals["income"] - $totals["expenses"], 2);

        return view("accounting.income-statement", compact("income", "expenses", "totals", "from", "to"));
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
                    "subtotal" => round((float) $groupRows->sum("balance"), 2),
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
