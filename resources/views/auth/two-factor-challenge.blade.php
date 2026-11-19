@extends("layouts.guest")
@section("title", __('app.two_factor'))
@section("auth_title", __('app.two_factor_challenge_title'))
@section("auth_subtitle", __('app.two_factor_challenge_subtitle'))

@section("content")
<form method="POST" action="{{ route("two-factor.verify") }}" id="code-form">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="code">{{ __('app.two_factor_code') }}</label>
        <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code"
            class="form-control form-control-lg text-center font-monospace @error("code") is-invalid @enderror" autofocus>
        @error("code") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3 collapse" id="recovery">
        <label class="form-label" for="recovery_code">{{ __('app.recovery_code') }}</label>
        <input id="recovery_code" type="text" name="recovery_code" maxlength="20" autocomplete="off" class="form-control text-uppercase font-monospace">
        <div class="form-text">{{ __('app.recovery_code_help') }}</div>
    </div>
    <button type="submit" class="btn btn-brand w-100 mb-3">{{ __('app.confirm') }}</button>
    <div class="text-center small">
        <a href="#recovery" data-bs-toggle="collapse">{{ __('app.use_recovery_code') }}</a>
        · <a href="{{ route('login') }}">{{ __('app.back_to_login') }}</a>
    </div>
</form>
@endsection
