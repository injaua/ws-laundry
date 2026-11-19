<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankDeposit;
use App\Models\Cheque;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Services\Payments\Cheques;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Cheques received (in hand, deposited, cleared, bounced) and bank deposits. */
class TreasuryController extends Controller
{
    public function __construct(private Cheques $cheques) {}

    public function cheques(Request $request): View
    {
        $status = $request->input("status", "in_hand");
        abort_unless(in_array($status, array_merge(Cheque::STATUSES, ["all"]), true), 404);

        $query = Cheque::with(["customer:id,name", "deposit", "payments.order:id,order_number"])
            ->when($status !== "all", fn ($q) => $q->where("status", $status))
            ->when($request->filled("q"), fn ($q) => $q->where(fn ($w) => $w->where("number", "like", "%" . $request->input("q") . "%")
                ->orWhereHas("customer", fn ($c) => $c->where("name", "like", "%" . $request->input("q") . "%"))));

        $totals = Cheque::selectRaw("status, count(*) as n, sum(amount) as total")->groupBy("status")->get()->keyBy("status");

        return view("treasury.cheques", [
            "cheques" => $query->orderBy($status === "in_hand" ? "cheque_date" : "updated_at", $status === "in_hand" ? "asc" : "desc")->paginate(30)->withQueryString(),
            "status" => $status,
            "totals" => $totals,
            // Bounced cheques keep their payments hidden from the scope: load them explicitly.
            "bouncedPayments" => $status === "bounced" || $status === "all"
                ? Payment::withoutGlobalScope("not_bounced")->whereNotNull("bounced_at")->with("order:id,order_number")->get()->groupBy("cheque_id")
                : collect(),
        ]);
    }

    public function clear(Cheque $cheque): RedirectResponse
    {
        $this->cheques->clear($cheque);

        return back()->with("success", __("app.cheque_cleared", ["cheque" => $cheque->label()]));
    }

    public function clearMany(Request $request): RedirectResponse
    {
        $data = $request->validate(["cheque_ids" => "required|array|min:1", "cheque_ids.*" => "integer"]);
        $count = 0;
        foreach (Cheque::whereIn("id", $data["cheque_ids"])->where("status", "deposited")->get() as $cheque) {
            $this->cheques->clear($cheque);
            $count++;
        }

        return back()->with("success", __("app.cheques_cleared", ["count" => $count]));
    }

    public function bounce(Request $request, Cheque $cheque): RedirectResponse
    {
        $data = $request->validate([
            "reason" => "required|string|max:255",
            "bounced_on" => "required|date|before_or_equal:today",
        ]);

        $result = $this->cheques->bounce($cheque, $data["reason"], $data["bounced_on"], $request->user());

        $redirect = back()->with("warning", __("app.cheque_bounced_done", [
            "cheque" => $cheque->label(),
            "orders" => $result["orders"]->pluck("order_number")->implode(", "),
        ]));

        return $result["warning"] ? $redirect->with("error", $result["warning"]) : $redirect;
    }

    public function deposits(): View
    {
        return view("treasury.deposits.index", [
            "deposits" => BankDeposit::with("user:id,name")->withCount("cheques")->latest("deposited_at")->latest("id")->paginate(25),
        ]);
    }

    public function createDeposit(): View
    {
        $cash = Account::findByCode("1.1");
        $cashBalance = $cash ? Money::round(JournalLine::where("account_id", $cash->id)->selectRaw("coalesce(sum(debit),0) - coalesce(sum(credit),0) as b")->value("b")) : null;

        return view("treasury.deposits.create", [
            "cheques" => $this->cheques->depositable(),
            "cashBalance" => $cashBalance,
            "waiting" => Cheque::inHand()->count(),
        ]);
    }

    public function storeDeposit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "deposited_at" => "required|date|before_or_equal:today",
            "slip_number" => "nullable|string|max:40",
            "cash_amount" => "nullable|numeric|min:0|max:1000000000",
            "cheque_ids" => "nullable|array",
            "cheque_ids.*" => ["integer", Rule::exists("cheques", "id")],
            "note" => "nullable|string|max:255",
        ]);

        $result = $this->cheques->deposit($data, $request->user());

        $redirect = redirect()->route("treasury.deposits.show", $result["deposit"])->with("success", __("app.deposit_recorded"));

        return $result["warning"] ? $redirect->with("error", $result["warning"]) : $redirect;
    }

    public function showDeposit(BankDeposit $deposit): View
    {
        return view("treasury.deposits.show", ["deposit" => $deposit->load(["user:id,name", "cheques.customer:id,name"])]);
    }
}
