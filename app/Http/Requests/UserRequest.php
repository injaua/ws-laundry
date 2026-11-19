<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Shared by users.store and users.update; authorisation comes from UserPolicy. */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route("user");

        return $target instanceof User
            ? $this->user()->can("update", $target)
            : $this->user()->can("create", User::class);
    }

    /**
     * Tidy the input before validating: e-mails are compared in lower case (a phone keyboard
     * capitalising the first letter must not fail the form).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            "name" => trim((string) $this->input("name")),
            "email" => mb_strtolower(trim((string) $this->input("email"))),
        ]);

        // The role field is locked (not sent) when editing one's own account: keep the current role.
        $target = $this->route("user");
        if ($target instanceof User && $target->is($this->user()) && !$this->filled("role")) {
            $this->merge(["role" => $target->role]);
        }
    }

    /** Field names used in the error messages. */
    public function attributes(): array
    {
        return [
            "name" => mb_strtolower(__("app.name")),
            "email" => "email",
            "password" => mb_strtolower(__("app.password")),
            "role" => mb_strtolower(__("app.role")),
            "store_id" => mb_strtolower(__("app.store")),
            "is_active" => mb_strtolower(__("app.active")),
        ];
    }

    public function rules(): array
    {
        $target = $this->route("user");
        $isUpdate = $target instanceof User;

        return array_filter([
            "name" => "required|string|min:2|max:255",
            "email" => ["required", "string", "lowercase", "email", "max:255", Rule::unique("users", "email")->ignore($target?->id)],
            "password" => [$isUpdate ? "nullable" : "required", "confirmed", Password::defaults()],
            "role" => ["required", Rule::in(UserPolicy::assignableRoles($this->user()))],
            "is_active" => $isUpdate ? "required|boolean" : null,
            // Admins choose the store (default: the one they are working in); owners always create in theirs.
            "store_id" => $this->user()->isAdmin() ? ["nullable", Rule::exists("stores", "id")] : null,
        ]);
    }
}
