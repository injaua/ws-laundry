@extends("layouts.guest")
@section("title", __('app.verify_email_title'))
@section("auth_title", __('app.verify_email_title'))
@section("auth_subtitle", __('app.verify_email_subtitle'))

@section("content")
@if (session("status") == "verification-link-sent")
    <div class="alert alert-success py-2 small mb-3">
        {{ __('app.verification_link_sent') }}
    </div>
@endif
<p class="text-muted small mb-4">
    {{ __('app.verify_email_body') }}
</p>
<div class="d-flex gap-2">
    <form method="POST" action="{{ route("verification.send") }}">
        @csrf
        <button type="submit" class="btn btn-brand">{{ __('app.resend_verification') }}</button>
    </form>
    <form method="POST" action="{{ route("logout") }}">
        @csrf
        <button type="submit" class="btn btn-outline-secondary">{{ __('app.logout') }}</button>
    </form>
</div>
@endsection
