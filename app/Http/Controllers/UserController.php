<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if (!auth()->user()->isAdmin()) {
            $query->where("role", "cashier");
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", "%{$search}%")
                  ->orWhere("email", "like", "%{$search}%");
            });
        }

        if ($role = $request->role) {
            $query->where("role", $role);
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        return view("users.index", compact("users"));
    }

    public function create()
    {
        $roles = auth()->user()->isAdmin() ? ["admin", "owner", "cashier"] : ["cashier"];
        return view("users.create", compact("roles"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:255",
            "email" => "required|string|lowercase|email|max:255|unique:users,email",
            "password" => ["required", "confirmed", Rules\Password::defaults()],
            "role" => "required|in:" . implode(",", auth()->user()->isAdmin() ? ["admin", "owner", "cashier"] : ["cashier"]),
        ]);

        User::create($validated + ["created_by" => auth()->id()]);

        return redirect()->route("users.index")
            ->with("success", __('app.user_created'));
    }

    public function edit(User $user)
    {
        abort_unless($user->manageableBy(auth()->user()), 403);

        return view("users.edit", compact("user"));
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->manageableBy(auth()->user()), 403);

        $validated = $request->validate([
            "name" => "required|string|max:255",
            "email" => "required|string|lowercase|email|max:255|unique:users,email," . $user->id,
            "password" => ["nullable", "confirmed", Rules\Password::defaults()],
            "role" => "required|in:" . implode(",", auth()->user()->isAdmin() ? ["admin", "owner", "cashier"] : ["cashier"]),
            "is_active" => "required|boolean",
        ]);

        if ($user->is($authUser = auth()->user())) {
            if ($validated["role"] !== "admin") {
                return back()->with("error", __('app.cannot_change_own_role'));
            }
            if (!$validated["is_active"]) {
                return back()->with("error", __('app.cannot_deactivate_self'));
            }
        }

        $demotingAdmin = $user->role === "admin"
            && ($validated["role"] !== "admin" || !$validated["is_active"]);

        if ($demotingAdmin && $user->isLastActiveAdmin()) {
            return back()->with("error", __('app.last_admin_protected'));
        }

        if (empty($validated["password"])) {
            unset($validated["password"]);
        }

        $user->update($validated);

        return redirect()->route("users.index")
            ->with("success", __('app.user_updated'));
    }

    public function destroy(User $user)
    {
        abort_unless($user->manageableBy(auth()->user()), 403);

        if ($user->is(auth()->user())) {
            return redirect()->route("users.index")
                ->with("error", __('app.cannot_delete_self'));
        }

        if ($user->isLastActiveAdmin()) {
            return redirect()->route("users.index")
                ->with("error", __('app.last_admin_protected'));
        }

        $user->delete();

        return redirect()->route("users.index")
            ->with("success", __('app.user_deleted'));
    }

    public function toggleActive(User $user)
    {
        if ($user->is(auth()->user())) {
            return back()->with("error", __('app.cannot_deactivate_self'));
        }

        if ($user->isLastActiveAdmin()) {
            return back()->with("error", __('app.last_admin_protected'));
        }

        $user->update(["is_active" => !$user->is_active]);

        return back()->with("success", __('app.user_toggled'));
    }
}
