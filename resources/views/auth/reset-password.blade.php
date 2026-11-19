@extends("layouts.guest")
@section("title", __('app.reset_password'))
@section("auth_title", __('app.reset_password'))
@section("auth_subtitle", __('app.reset_password'))

@section("content")
<form method="POST" action="{{ route("password.store") }}">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route("token") }}">
    <div class="mb-3">
        <label class="form-label" for="email">{{ __('app.email') }}</label>
        <input id="email" type="email" name="email" class="form-control @error("email") is-invalid @enderror"
            value="{{ old("email", $request->email) }}" required autofocus autocomplete="username">
        @error("email") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">{{ __('app.password') }}</label>
        <div class="input-group has-validation">
            <input id="password" type="password" name="password"
                class="form-control @error("password") is-invalid @enderror"
                required autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="{{ __('app.show_password') }}" aria-pressed="false"><i class="bi bi-eye"></i></button>
            @error("password") <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="mb-4">
        <label class="form-label" for="password_confirmation">{{ __('app.confirm_password') }}</label>
        <div class="input-group has-validation">
            <input id="password_confirmation" type="password" name="password_confirmation"
                class="form-control @error("password_confirmation") is-invalid @enderror"
                required autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password_confirmation" aria-label="{{ __('app.show_password') }}" aria-pressed="false"><i class="bi bi-eye"></i></button>
            @error("password_confirmation") <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <button type="submit" class="btn btn-brand w-100">{{ __('app.reset_password') }}</button>
</form>
@endsection
