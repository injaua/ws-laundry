<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalancoController extends Controller
{
    public function index(Request $request)
    {
        $to = $request->to ? Carbon::parse($request->to) : today();

        $rows = DB::table("accounts")
            ->join("journal_lines", "journal_lines.account_id", "=", "accounts.id")
            ->join("journal_entries", "journal_entries.id", "=", "journal_lines.journal_entry_id")
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

        $rendimentos = (float) $byType->get("revenue", collect())->sum("balance");
        $gastos = (float) $byType->get("expense", collect())->sum("balance");
        $resultado = round($rendimentos - $gastos, 2);

        $activo = $byType->get("asset", collect());
        $passivo = $byType->get("liability", collect());
        $capital = $byType->get("equity", collect());

        $totals = [
            "activo" => round((float) $activo->sum("balance"), 2),
            "passivo" => round((float) $passivo->sum("balance"), 2),
            "capital" => round((float) $capital->sum("balance") + $resultado, 2),
        ];
        $totals["passivo_capital"] = round($totals["passivo"] + $totals["capital"], 2);
        $balanced = abs($totals["activo"] - $totals["passivo_capital"]) < 0.005;

        return view("accounting.balanco", compact(
            "activo", "passivo", "capital", "resultado", "totals", "balanced", "to"
        ));
    }
}
