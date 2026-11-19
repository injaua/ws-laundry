@extends("layouts.guest")
@section("title", "Konfirmasi Password")
@section("auth_title", "Konfirmasi keamanan")
@section("auth_subtitle", __('app.confirm_password_subtitle'))

@section("content")
<form method="POST" action="{{ route("password.confirm") }}">
    @csrf
    <div class="mb-4">
        <label class="form-label" for="password">Password</label>
        <input id="password" type="password" name="password"
            class="form-control @error("password") is-invalid @enderror"
            required autocomplete="current-password">
        @error("password") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <button type="submit" class="btn btn-brand w-100">{{ __('app.confirm') }}</button>
</form>
@endsection
