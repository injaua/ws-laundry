<?php

namespace App\Http\Controllers;

use App\Support\CurrentStore;
use App\Support\Documents\ReportDownload;
use App\Support\Documents\ReportTable;
use App\Support\Money;
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
                    $difference = Money::sub($row->total_debit, $row->total_credit);
                    $naturalTypes = ["asset", "expense"];
                    $balance = in_array($row->type, $naturalTypes) ? $difference : -$difference;

                    return (object) [
                        "code" => $row->code,
                        "name" => $row->name,
                        "balance" => $balance,
                    ];
                })
                ->filter(fn ($row) => !Money::isZero($row->balance))
                ->values();
        });

        $income = Money::round($byType->get("revenue", collect())->sum("balance"));
        $expenses = Money::round($byType->get("expense", collect())->sum("balance"));
        $netResult = Money::sub($income, $expenses);

        $assets = $byType->get("asset", collect());
        $liabilities = $byType->get("liability", collect());
        $capital = $byType->get("equity", collect());

        $totals = [
            "assets" => Money::round($assets->sum("balance")),
            "liabilities" => Money::round($liabilities->sum("balance")),
            "capital" => Money::add(Money::sum($capital->pluck("balance")), $netResult),
        ];
        $totals["liabilities_equity"] = Money::add($totals["liabilities"], $totals["capital"]);
        $totals["difference"] = Money::sub($totals["assets"], $totals["liabilities_equity"]);
        $balanced = Money::isZero($totals["difference"]);

        if ($format = ReportDownload::requested($request)) {
            return ReportDownload::make($this->table($assets, $liabilities, $capital, $netResult, $totals, $to), $format);
        }

        return view("accounting.balance-sheet", compact(
            "assets", "liabilities", "capital", "netResult", "totals", "balanced", "to"
        ));
    }

    private function table($assets, $liabilities, $capital, float $netResult, array $totals, Carbon $to): ReportTable
    {
        $table = new ReportTable(
            __("app.balance_sheet"),
            __("app.to_date") . " " . $to->format(date_fmt()),
            [[__("app.code"), "text"], [__("app.account"), "text"], [__("app.balance"), "money"]],
            __("app.balance_sheet") . " " . $to->format("Y-m-d"),
        );

        $section = function (string $title, $rows, string $totalLabel, float $total, ?float $result = null) use ($table) {
            $table->section($title);
            foreach ($rows as $row) {
                $table->row([$row->code, $row->name, $row->balance], "indent");
            }
            if ($result !== null) {
                $table->row(["", __("app.net_result_for_period"), $result], "indent");
            }
            $table->row(["", $totalLabel, $total], "subtotal");
        };

        $section(__("app.assets"), $assets, __("app.total_assets"), $totals["assets"]);
        $section(__("app.liabilities"), $liabilities, __("app.total_liabilities"), $totals["liabilities"]);
        $section(__("app.equity"), $capital, __("app.total_equity"), $totals["capital"], $netResult);

        return $table->row(["", __("app.total_liabilities_and_equity"), $totals["liabilities_equity"]], "total");
    }
}
