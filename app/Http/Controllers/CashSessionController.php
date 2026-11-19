<?php

namespace App\Http\Controllers;

use App\Actions\CashSessions\CloseCashSession;
use App\Actions\CashSessions\OpenCashSession;
use App\Actions\CashSessions\RecordCashMovement;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CashSessionController extends Controller
{
    /** Shift history: operators see their own, managers and the accountant see everyone's. */
    public function index(Request $request)
    {
        Gate::authorize("viewAny", CashSession::class);

        $user = $request->user();
        $isManager = $user->seesFinance();

        $query = CashSession::with(["user", "closer", "counts"])->latest("opened_at");

        if (!$isManager) {
            $query->where("user_id", $user->id);
        } elseif ($operator = $request->integer("user_id")) {
            $query->where("user_id", $operator);
        }

        if (in_array($request->status, [CashSession::STATUS_OPEN, CashSession::STATUS_CLOSED], true)) {
            $query->where("status", $request->status);
        }

        if ($request->filled("date")) {
            $query->whereDate("opened_at", $request->date("date"));
        }

        if ($request->boolean("with_difference")) {
            $query->whereHas("counts", fn ($q) => $q->where("difference", "!=", 0));
        }

        $sessions = $query->paginate(20)->withQueryString();
        $operators = $isManager ? User::whereIn("id", CashSession::select("user_id"))->orderBy("name")->get() : collect();
        $current = CashSession::currentFor($user);

        return view("cash-sessions.index", compact("sessions", "operators", "current", "isManager"));
    }

    /** "My till": the open form, or the running shift with its movements. */
    public function current(Request $request)
    {
        $session = CashSession::currentFor($request->user());

        if (!$session) {
            Gate::authorize("open", CashSession::class);

            return view("cash-sessions.open");
        }

        return redirect()->route("cash-sessions.show", $session);
    }

    public function store(Request $request, OpenCashSession $open)
    {
        Gate::authorize("open", CashSession::class);

        $validated = $request->validate([
            "opening_float" => "required|numeric|min:0|max:100000000",
        ]);

        $session = $open->handle($request->user(), (float) $validated["opening_float"]);

        return redirect()->route("cash-sessions.show", $session)
            ->with("success", __("app.cash_session_opened"));
    }

    public function show(Request $request, CashSession $cashSession)
    {
        Gate::authorize("view", $cashSession);

        $cashSession->load(["user", "closer", "counts", "movements.user", "payments.order"]);
        $canSeeExpected = $request->user()->can("seeExpected", $cashSession);
        $expected = $canSeeExpected && $cashSession->isOpen() ? $cashSession->expectedByMethod() : null;
        $paymentTotals = $cashSession->payments->groupBy("method")->map(fn ($p) => [
            "count" => $p->count(),
            "total" => (float) $p->sum("amount"),
        ]);

        return view("cash-sessions.show", [
            "session" => $cashSession,
            "canSeeExpected" => $canSeeExpected,
            "expected" => $expected,
            "paymentTotals" => $paymentTotals,
        ]);
    }

    public function storeMovement(Request $request, CashSession $cashSession, RecordCashMovement $record)
    {
        Gate::authorize("move", $cashSession);

        $validated = $request->validate([
            "type" => ["required", Rule::in(CashMovement::TYPES)],
            "amount" => "required|numeric|min:1",
            "reason" => "required|string|max:255",
        ]);

        $record->handle($cashSession, $request->user(), $validated["type"], (float) $validated["amount"], $validated["reason"]);

        return redirect()->route("cash-sessions.show", $cashSession)
            ->with("success", __($validated["type"] === CashMovement::TYPE_WITHDRAWAL ? "app.cash_withdrawal_recorded" : "app.cash_deposit_recorded"));
    }

    public function closeForm(CashSession $cashSession)
    {
        Gate::authorize("close", $cashSession);

        return view("cash-sessions.close", [
            "session" => $cashSession->load("user"),
            "references" => $cashSession->paymentReferences(),
        ]);
    }

    public function close(Request $request, CashSession $cashSession, CloseCashSession $close)
    {
        Gate::authorize("close", $cashSession);

        $rules = ["closing_note" => "nullable|string|max:1000"];
        foreach (Payment::METHODS as $method) {
            $rules["counted.$method"] = "required|numeric|min:0";
        }
        $validated = $request->validate($rules);

        $result = $close->handle($cashSession, $request->user(), $validated["counted"], $validated["closing_note"] ?? null);

        $redirect = redirect()->route("cash-sessions.show", $result["session"])
            ->with("success", __("app.cash_session_closed"));

        return $result["warning"] ? $redirect->with("warning", $result["warning"]) : $redirect;
    }
}
