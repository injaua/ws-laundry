@extends("layouts.admin")
@section("title", __('app.edit_user'))
@section("page_title", __('app.edit_user'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route("users.update", $user) }}" method="POST">
                    @csrf @method("PATCH")

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old("name", $user->name) }}" class="form-control @error("name") is-invalid @enderror" required autofocus>
                        @error("name")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.email') }} <span class="text-danger">*</span></label>
                        <input type="email" name="email" value="{{ old("email", $user->email) }}" class="form-control @error("email") is-invalid @enderror" required>
                        @error("email")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.role') }} <span class="text-danger">*</span></label>
                        @if(auth()->user()->isAdmin())
                        <select name="role" class="form-select @error("role") is-invalid @enderror" required @disabled($user->is(auth()->user()))>
                            <option value="admin" @selected(old("role", $user->role) == "admin")>{{ __('app.role_admin') }}</option>
                            <option value="owner" @selected(old("role", $user->role) == "owner")>{{ __('app.role_owner') }}</option>
                            <option value="cashier" @selected(old("role", $user->role) == "cashier")>{{ __('app.role_cashier') }}</option>
                        </select>
                        @else
                        <input type="hidden" name="role" value="cashier">
                        <div class="form-control-plaintext border rounded px-3 py-2">{{ __('app.role_cashier') }}</div>
                        @endif
                        @if($user->is(auth()->user()))
                            <div class="form-text">{{ __('app.cannot_change_own_role') }}</div>
                        @endif
                        @error("role")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.new_password') }}</label>
                        <input type="password" name="password" class="form-control @error("password") is-invalid @enderror" autocomplete="new-password" placeholder="{{ __('app.password_keep_blank') }}">
                        @error("password")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.confirm_password') }}</label>
                        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                            @checked(old("is_active", $user->is_active))
                            @disabled($user->is(auth()->user()))>
                        <label class="form-check-label" for="is_active">{{ __('app.active') }}</label>
                        @if($user->is(auth()->user()))
                            <div class="form-text">{{ __('app.cannot_deactivate_self') }}</div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route("users.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
