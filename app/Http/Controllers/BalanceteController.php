<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceteController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : today()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to) : today();

        $rows = DB::table("accounts")
            ->join("journal_lines", "journal_lines.account_id", "=", "accounts.id")
            ->join("journal_entries", "journal_entries.id", "=", "journal_lines.journal_entry_id")
            ->where("accounts.is_group", false)
            ->whereBetween("journal_entries.entry_date", [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->groupBy("accounts.id", "accounts.code", "accounts.name", "accounts.type")
            ->orderBy("accounts.code")
            ->select(
                "accounts.id",
                "accounts.code",
                "accounts.name",
                "accounts.type",
                DB::raw("SUM(journal_lines.debit) as total_debit"),
                DB::raw("SUM(journal_lines.credit) as total_credit")
            )
            ->get()
            ->map(function ($row) {
                $difference = (float) $row->total_debit - (float) $row->total_credit;
                $row->balance_debit = $difference > 0 ? $difference : 0;
                $row->balance_credit = $difference < 0 ? -$difference : 0;

                return $row;
            });

        $totals = [
            "debit" => (float) $rows->sum("total_debit"),
            "credit" => (float) $rows->sum("total_credit"),
            "balance_debit" => (float) $rows->sum("balance_debit"),
            "balance_credit" => (float) $rows->sum("balance_credit"),
        ];

        return view("accounting.balancete", compact("rows", "totals", "from", "to"));
    }
}
