<?php

namespace App\Http\Controllers;

use App\Services\TrialBalanceService;
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

        return view("accounting.trial-balance", compact("rows", "totals", "from", "to"));
    }
}
