<?php

namespace App\Http\Controllers;

use App\Services\TrialBalanceService;
use App\Support\Documents\ReportDownload;
use App\Support\Documents\ReportTable;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TrialBalanceController extends Controller
{
    public function index(Request $request, TrialBalanceService $trialBalance)
    {
        $from = $request->from ? Carbon::parse($request->from) : today()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to) : today();

        $rows = $trialBalance->rows($from, $to);
        $totals = $trialBalance->totals($rows);

        if ($format = ReportDownload::requested($request)) {
            return ReportDownload::make($this->table($rows, $totals, $from, $to), $format);
        }

        return view("accounting.trial-balance", compact("rows", "totals", "from", "to"));
    }

    private function table($rows, array $totals, Carbon $from, Carbon $to): ReportTable
    {
        $table = new ReportTable(__("app.trial_balance"), $from->format(date_fmt()) . " – " . $to->format(date_fmt()), [
            [__("app.code"), "text"], [__("app.account"), "text"],
            [__("app.total_debit"), "money"], [__("app.total_credit"), "money"],
            [__("app.balance_debit"), "money"], [__("app.balance_credit"), "money"],
        ], __("app.trial_balance") . " " . $from->format("Y-m-d") . " " . $to->format("Y-m-d"));

        foreach ($rows as $row) {
            $table->row([$row->code, $row->name, $row->total_debit, $row->total_credit, $row->balance_debit, $row->balance_credit]);
        }

        return $table->row(["", __("app.total"), $totals["debit"], $totals["credit"], $totals["balance_debit"], $totals["balance_credit"]], "total");
    }
}
