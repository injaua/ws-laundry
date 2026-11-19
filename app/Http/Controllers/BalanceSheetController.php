<?php

namespace App\Http\Controllers;

use App\Support\CurrentStore;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceSheetController extends Controller
{
    public function index(Request $request)
    {
        $to = $request->to ? Carbon::parse($request->to) : today();

        $rows = DB::table("accounts")
            ->join("journal_lines", "journal_lines.account_id", "=", "accounts.id")
            ->join("journal_entries", "journal_entries.id", "=", "journal_lines.journal_entry_id")
            ->where("journal_entries.store_id", CurrentStore::id())
            ->where("accounts.is_group", false)
            ->where("journal_entries.entry_date", "<=", $to->copy()->endOfDay()->toDateTimeString())
            ->groupBy("accounts.id", "accounts.code", "accounts.name", "accounts.type")
            ->orderBy("accounts.code")
            ->select(
                "accounts.code",
                "accounts.name",
                "accounts.type",
                DB::raw("SUM(journal_lines.debit) as total_debit"),
                DB::raw("SUM(journal_lines.credit) as total_credit")
            )
            ->get();

        $byType = $rows->groupBy("type")->map(function ($group) {
            return $group
                ->map(function ($row) {
                    $difference = (float) $row->total_debit - (float) $row->total_credit;
                    $naturalTypes = ["asset", "expense"];
                    $balance = in_array($row->type, $naturalTypes) ? $difference : -$difference;

                    return (object) [
                        "code" => $row->code,
                        "name" => $row->name,
                        "balance" => round($balance, 2),
                    ];
                })
                ->filter(fn ($row) => abs($row->balance) > 0.004)
                ->values();
        });

        $income = (float) $byType->get("revenue", collect())->sum("balance");
        $expenses = (float) $byType->get("expense", collect())->sum("balance");
        $netResult = round($income - $expenses, 2);

        $assets = $byType->get("asset", collect());
        $liabilities = $byType->get("liability", collect());
        $capital = $byType->get("equity", collect());

        $totals = [
            "assets" => round((float) $assets->sum("balance"), 2),
            "liabilities" => round((float) $liabilities->sum("balance"), 2),
            "capital" => round((float) $capital->sum("balance") + $netResult, 2),
        ];
        $totals["liabilities_equity"] = round($totals["liabilities"] + $totals["capital"], 2);
        $balanced = abs($totals["assets"] - $totals["liabilities_equity"]) < 0.005;

        return view("accounting.balance-sheet", compact(
            "assets", "liabilities", "capital", "netResult", "totals", "balanced", "to"
        ));
    }
}
