@extends("layouts.guest")
@section("title", __('app.login'))
@section("auth_title", __('app.welcome_back'))
@section("auth_subtitle", __('app.login_subtitle'))

@section("content")
<form method="POST" action="{{ route("login") }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="email">{{ __('app.email') }}</label>
        <input id="email" type="email" name="email" class="form-control @error("email") is-invalid @enderror"
            value="{{ old("email") }}" required autofocus autocomplete="username">
        @error("email") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">{{ __('app.password') }}</label>
        <div class="input-group has-validation">
            <input id="password" type="password" name="password"
                class="form-control @error("password") is-invalid @enderror"
                required autocomplete="current-password">
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="{{ __('app.show_password') }}" aria-pressed="false"><i class="bi bi-eye"></i></button>
            @error("password") <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
            <input id="remember_me" type="checkbox" name="remember" class="form-check-input">
            <label class="form-check-label small" for="remember_me">{{ __('app.remember_me') }}</label>
        </div>
        @if (Route::has("password.request"))
            <a href="{{ route("password.request") }}" class="auth-link small">{{ __('app.forgot_password') }}</a>
        @endif
    </div>
    <button type="submit" class="btn btn-brand w-100 mb-3">{{ __('app.login') }}</button>
    @if ($canRegister ?? false)
        <p class="text-center small text-muted mb-0">
            {{ __('app.dont_have_account') }}
            <a href="{{ route("register") }}" class="auth-link">{{ __('app.register') }}</a>
        </p>
    @endif
</form>
@endsection
