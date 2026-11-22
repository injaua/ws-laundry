<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield("title", __('app.login')) - {{ \App\Support\SystemSettings::companyName() }}</title>
    <link rel="icon" href="{{ \App\Support\SystemSettings::faviconUrl() }}">

    @include("partials.bootstrap")
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(["resources/css/guest.css", "resources/js/guest.js"])
    @stack("styles")
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-side">
            <img class="brand-logo" src="{{ \App\Support\SystemSettings::logoDarkUrl() }}" alt="{{ \App\Support\SystemSettings::companyName() }}">
            <h2>{{ \App\Support\SystemSettings::legalName() }}</h2>
            <p>{{ __('app.auth_sidebar_title') }}</p>
            <div class="features">
                <div class="feature-item"><i class="bi bi-check2"></i><span>{{ __('app.auth_feature_1') }}</span></div>
                <div class="feature-item"><i class="bi bi-check2"></i><span>{{ __('app.auth_feature_2') }}</span></div>
                <div class="feature-item"><i class="bi bi-check2"></i><span>{{ __('app.auth_feature_3') }}</span></div>
            </div>
        </div>
        <div class="auth-main">
            <div class="auth-lang-switcher dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-translate"></i> {{ strtoupper(app()->getLocale()) }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach(\App\Support\Languages::active() as $lang)
                        <li><a class="dropdown-item {{ app()->getLocale() == $lang->code ? 'active' : '' }}" href="{{ route('language.switch', $lang->code) }}">{{ $lang->label() }}</a></li>
                    @endforeach
                </ul>
            </div>
            <img class="auth-logo" src="{{ \App\Support\SystemSettings::logoUrl() }}" alt="{{ \App\Support\SystemSettings::companyName() }}">
            <div class="auth-header">
                @hasSection("auth_title")
                    <h4>@yield("auth_title")</h4>
                    <p>@yield("auth_subtitle")</p>
                @endif
            </div>
            @if(session("status"))
                <div class="alert alert-success py-2 small mb-3">{{ session("status") }}</div>
            @endif
            @if(session("error"))
                <div class="alert alert-danger py-2 small mb-3">{{ session("error") }}</div>
            @endif
            @yield("content")
        </div>
    </div>
    @stack("scripts")
</body>
</html>
