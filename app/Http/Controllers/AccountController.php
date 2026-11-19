<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::withExists(["lines", "children"])->orderBy("code")->get();

        return view("accounting.accounts.index", compact("accounts"));
    }

    public function create()
    {
        $groups = Account::where("is_group", true)->orderBy("code")->get();

        return view("accounting.accounts.create", compact("groups"));
    }

    public function store(Request $request)
    {
        $validated = $this->validateAccount($request);

        Account::create($validated);

        return redirect()->route("accounting.accounts.index")
            ->with("success", __("app.account_created"));
    }

    public function edit(Account $account)
    {
        $groups = Account::where("is_group", true)->whereKeyNot($account->id)->orderBy("code")->get();

        return view("accounting.accounts.edit", [
            "account" => $account,
            "groups" => $groups,
        ]);
    }

    public function update(Request $request, Account $account)
    {
        $validated = $this->validateAccount($request, $account);

        if ($account->is_group && isset($validated["is_group"]) && !$validated["is_group"] && $account->children()->exists()) {
            return back()->withInput()->withErrors([
                "is_group" => __("app.account_has_children"),
            ]);
        }

        $account->update($validated);

        return redirect()->route("accounting.accounts.index")
            ->with("success", __("app.account_updated"));
    }

    public function destroy(Account $account)
    {
        if ($account->lines()->exists() || $account->children()->exists()) {
            return redirect()->route("accounting.accounts.index")
                ->with("error", __("app.account_in_use"));
        }

        $account->delete();

        return redirect()->route("accounting.accounts.index")
            ->with("success", __("app.account_deleted"));
    }

    private function validateAccount(Request $request, ?Account $account = null): array
    {
        return $request->validate([
            "code" => [
                "required", "string", "max:20",
                Rule::unique("accounts", "code")->ignore($account),
            ],
            "name" => "required|string|max:120",
            "type" => "required|in:" . implode(",", Account::TYPES),
            "parent_id" => "nullable|" . Rule::exists("accounts", "id")->where("is_group", true),
            "is_group" => "nullable|boolean",
            "is_active" => "nullable|boolean",
        ]);
    }
}
