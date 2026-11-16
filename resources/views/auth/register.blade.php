@extends("layouts.guest")
@section("title", __('app.register'))
@section("auth_title", __('app.create_new_account'))
@section("auth_subtitle", __('app.register_subtitle'))

@section("content")
<form method="POST" action="{{ route("register") }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="name">{{ __('app.name') }}</label>
        <input id="name" type="text" name="name" class="form-control @error("name") is-invalid @enderror"
            value="{{ old("name") }}" required autofocus autocomplete="name">
        @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="email">{{ __('app.email') }}</label>
        <input id="email" type="email" name="email" class="form-control @error("email") is-invalid @enderror"
            value="{{ old("email") }}" required autocomplete="username">
        @error("email") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">{{ __('app.password') }}</label>
        <div class="input-group has-validation">
            <input id="password" type="password" name="password"
                class="form-control @error("password") is-invalid @enderror"
                required autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button" onclick="togglePw('password', this)" aria-label="{{ __('app.show_password') }}" aria-pressed="false"><i class="bi bi-eye"></i></button>
            @error("password") <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="mb-4">
        <label class="form-label" for="password_confirmation">{{ __('app.confirm_password') }}</label>
        <div class="input-group has-validation">
            <input id="password_confirmation" type="password" name="password_confirmation"
                class="form-control @error("password_confirmation") is-invalid @enderror"
                required autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button" onclick="togglePw('password_confirmation', this)" aria-label="{{ __('app.show_password') }}" aria-pressed="false"><i class="bi bi-eye"></i></button>
            @error("password_confirmation") <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <button type="submit" class="btn btn-brand w-100 mb-3">{{ __('app.register') }}</button>
    <p class="text-center small text-muted mb-0">
        {{ __('app.already_have_account') }}
        <a href="{{ route("login") }}" class="auth-link">{{ __('app.login') }}</a>
    </p>
</form>
@endsection
