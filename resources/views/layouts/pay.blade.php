<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield("title", __('app.pay_title')) - {{ \App\Support\SystemSettings::companyName() }}</title>
    <link rel="icon" href="{{ \App\Support\SystemSettings::faviconUrl() }}">
    @include("partials.bootstrap")
    @vite(["resources/css/pay.css", "resources/js/guest.js"])
</head>
<body class="py-4 px-3">
    <div class="pay-card">
        <div class="text-center mb-3">
            <a href="{{ url('/') }}" class="text-decoration-none brand fs-5"><img src="{{ \App\Support\SystemSettings::logoUrl() }}" alt="" height="36" class="me-1" data-hide-broken>{{ \App\Support\SystemSettings::companyName() }}</a>
        </div>
        @yield("content")
        <p class="text-center small text-muted mt-3"><i class="bi bi-shield-lock"></i> {{ __('app.pay_secure_note') }}</p>
    </div>
    @stack("scripts")
</body>
</html>
