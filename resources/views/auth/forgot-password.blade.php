@extends("layouts.guest")
@section("title", __('app.forgot_password_title'))
@section("auth_title", __('app.forgot_password'))
@section("auth_subtitle", __('app.forgot_password_desc'))

@section("content")
<form method="POST" action="{{ route("password.email") }}">
    @csrf
    <div class="mb-4">
        <label class="form-label" for="email">{{ __('app.email') }}</label>
        <input id="email" type="email" name="email" class="form-control @error("email") is-invalid @enderror"
            value="{{ old("email") }}" required autofocus>
        @error("email") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <button type="submit" class="btn btn-brand w-100 mb-3">{{ __('app.send_reset_link') }}</button>
    <p class="text-center small text-muted mb-0">
        <a href="{{ route("login") }}" class="auth-link">{{ __('app.back') }}</a>
    </p>
</form>
@endsection
