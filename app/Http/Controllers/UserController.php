<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Store;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Security\UserSessions;
use App\Support\CurrentStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $actor = auth()->user();
        $query = User::query()->with("store");

        if (!$actor->isAdmin()) {
            // An owner sees the staff of their own store only.
            $query->inStore($actor->store_id)->whereIn("role", ["accountant", "cashier", "courier"]);
        } elseif ($storeId = $request->integer("store")) {
            $query->where("store_id", $storeId);
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
        $stores = $actor->isAdmin() ? Store::orderBy("name")->get() : collect();

        return view("users.index", compact("users", "stores"));
    }

    public function create()
    {
        $roles = UserPolicy::assignableRoles(auth()->user());
        $stores = auth()->user()->isAdmin() ? Store::active()->orderBy("name")->get() : collect();

        $currentStoreId = CurrentStore::id();

        return view("users.create", compact("roles", "stores", "currentStoreId") + ["user" => new User]);
    }

    public function store(UserRequest $request)
    {
        try {
            $user = User::create(["store_id" => $this->storeFor($request->validated())] + $request->validated() + ["created_by" => auth()->id()]);
        } catch (\Throwable $e) {
            // Validation passed but saving failed: say so on the form instead of an error page.
            report($e);

            return back()->withInput($request->except(["password", "password_confirmation"]))
                ->with("error", __("app.user_save_failed"));
        }

        return redirect()->route("users.index")
            ->with("success", __("app.user_created_named", ["name" => $user->name]));
    }

    public function edit(User $user)
    {
        Gate::authorize("update", $user);

        $stores = auth()->user()->isAdmin() ? Store::orderBy("name")->get() : collect();

        $roles = UserPolicy::assignableRoles(auth()->user());

        return view("users.edit", compact("user", "stores", "roles"));
    }

    public function update(UserRequest $request, User $user)
    {
        $validated = $request->validated();

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

        $validated["store_id"] = $authUser->isAdmin() ? $this->storeFor($validated) : $user->store_id;
        try {
            $user->update($validated);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->except(["password", "password_confirmation"]))
                ->with("error", __("app.user_save_failed"));
        }

        return redirect()->route("users.index")
            ->with("success", __('app.user_updated'));
    }

    public function destroy(User $user)
    {
        Gate::authorize("delete", $user);

        if ($user->is(auth()->user())) {
            return redirect()->route("users.index")
                ->with("error", __('app.cannot_delete_self'));
        }

        if ($user->isLastActiveAdmin()) {
            return redirect()->route("users.index")
                ->with("error", __('app.last_admin_protected'));
        }

        // An account with history is deactivated, never deleted: its actions must keep their author.
        if ($user->hasHistory()) {
            return redirect()->route("users.index")
                ->with("error", __('app.user_has_history'));
        }

        $user->delete();

        return redirect()->route("users.index")
            ->with("success", __('app.user_deleted'));
    }

    /** Admins work in every store; anyone else belongs to the chosen store (admin) or the owner's. */
    private function storeFor(array $validated): ?int
    {
        if ($validated["role"] === "admin") {
            return null;
        }

        $actor = auth()->user();

        return $actor->isAdmin() ? (int) ($validated["store_id"] ?? CurrentStore::id()) : $actor->store_id;
    }

    public function toggleActive(User $user)
    {
        Gate::authorize("toggleActive", $user);

        if ($user->is(auth()->user())) {
            return back()->with("error", __('app.cannot_deactivate_self'));
        }

        if ($user->isLastActiveAdmin()) {
            return back()->with("error", __('app.last_admin_protected'));
        }

        $user->update(["is_active" => !$user->is_active]);
        if (!$user->is_active) {
            app(UserSessions::class)->revoke($user); // logged out everywhere now
        }

        return back()->with("success", __('app.user_toggled'));
    }
}
