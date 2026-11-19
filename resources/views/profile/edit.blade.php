@extends("layouts.admin")
@section("title", __('app.profile'))
@section("page_title", __('app.profile'))

@section("content")
<div class="row">
    <div class="col-lg-8">
        {{-- Profile information --}}
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person me-1"></i> {{ __('app.profile_information') }}
            </div>
            <div class="card-body">
                <form method="post" action="{{ route("profile.update") }}" class="mb-0">
                    @csrf
                    @method("patch")

                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('app.name') }}</label>
                        <input id="name" name="name" type="text"
                            class="form-control @error("name", "updateProfileInformation") is-invalid @enderror"
                            value="{{ old("name", $user->name) }}" required autofocus autocomplete="name">
                        @error("name", "updateProfileInformation")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">{{ __('app.email') }}</label>
                        <input id="email" name="email" type="email"
                            class="form-control @error("email", "updateProfileInformation") is-invalid @enderror"
                            value="{{ old("email", $user->email) }}" required autocomplete="username">
                        @error("email", "updateProfileInformation")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> {{ __('app.save') }}
                        </button>
                        @if (session("status") === "profile-updated")
                            <span class="text-success small">
                                <i class="bi bi-check-circle"></i> {{ __('app.save') }}!
                            </span>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- Update Password --}}
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-shield-lock me-1"></i> {{ __('app.update_password') }}
            </div>
            <div class="card-body">
                <form method="post" action="{{ route("password.update") }}" class="mb-0">
                    @csrf
                    @method("put")

                    <div class="mb-3">
                        <label for="current_password" class="form-label">{{ __('app.current_password') }}</label>
                        <input id="current_password" name="current_password" type="password"
                            class="form-control @error("current_password", "updatePassword") is-invalid @enderror"
                            autocomplete="current-password">
                        @error("current_password", "updatePassword")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">{{ __('app.password') }}</label>
                        <input id="password" name="password" type="password"
                            class="form-control @error("password", "updatePassword") is-invalid @enderror"
                            autocomplete="new-password">
                        @error("password", "updatePassword")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">{{ __('app.confirm_password') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password"
                            class="form-control @error("password_confirmation", "updatePassword") is-invalid @enderror"
                            autocomplete="new-password">
                        @error("password_confirmation", "updatePassword")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> {{ __('app.save') }}
                        </button>
                        @if (session("status") === "password-updated")
                            <span class="text-success small">
                                <i class="bi bi-check-circle"></i> {{ __('app.save') }}!
                            </span>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Account information --}}
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-info-circle me-1"></i> {{ __('app.account') }}
            </div>
            <div class="card-body text-center">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3 avatar-80"
                   >
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <h5 class="fw-semibold mb-0">{{ $user->name }}</h5>
                <small class="text-muted">{{ $user->email }}</small>
                <hr>
                <div class="text-start small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">{{ __('app.joined') }}</span>
                        <span>{{ $user->created_at->format(date_fmt()) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Account security (self-deletion is disabled: staff accounts keep the audit trail) --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-shield-lock me-1"></i> {{ __('app.account_security') }}</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span>{{ __('app.two_factor') }}</span>
                    @if($user->hasTwoFactor())<span class="badge bg-success">{{ __('app.enabled') }}</span>@else<span class="badge bg-secondary">{{ __('app.disabled') }}</span>@endif
                </div>
                @if($user->password_changed_at)
                <div class="d-flex justify-content-between mb-2 text-muted"><span>{{ __('app.password_changed_at') }}</span><span>{{ $user->password_changed_at->format(date_fmt()) }}</span></div>
                @endif
                <a href="{{ route("profile.security") }}" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-shield-check"></i> {{ __('app.manage_security') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
