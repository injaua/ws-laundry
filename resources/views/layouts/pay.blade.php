<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield("title", __('app.pay_title')) - {{ config("app.name") }}</title>
    <link rel="icon" href="{{ \App\Support\SystemSettings::faviconUrl() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap{{ is_rtl() ? '.rtl' : '' }}.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(["resources/css/pay.css", "resources/js/guest.js"])
</head>
<body class="py-4 px-3">
    <div class="pay-card">
        <div class="text-center mb-3">
            <a href="{{ url('/') }}" class="text-decoration-none brand fs-5"><img src="{{ \App\Support\SystemSettings::logoUrl() }}" alt="" height="36" class="me-1" data-hide-broken>{{ config("app.name") }}</a>
        </div>
        @yield("content")
        <p class="text-center small text-muted mt-3"><i class="bi bi-shield-lock"></i> {{ __('app.pay_secure_note') }}</p>
    </div>
    @stack("scripts")
</body>
</html>
