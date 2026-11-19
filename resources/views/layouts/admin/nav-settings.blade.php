{{-- Sidebar · Users, settings and the public site (admin / owner). --}}
<div class="nav-section mt-3">{{ __('app.front_page') }}</div>
@if(auth()->user()->isAdmin())
<a href="{{ route("admin.stores.index") }}" class="nav-link {{ request()->routeIs("admin.stores.*") ? "active" : "" }}">
    <i class="bi bi-shop-window"></i> {{ __('app.stores') }}
</a>
@endif
@if(auth()->user()->hasRole(["admin", "owner"]))
<a href="{{ route("users.index") }}" class="nav-link {{ request()->routeIs("users.*") ? "active" : "" }}">
    <i class="bi bi-person-gear"></i> {{ __('app.users') }}
</a>
@php($settingsActive = request()->routeIs("admin.landing-settings.*") || request()->routeIs("admin.business-settings.*") || request()->routeIs("admin.notifications.*") || request()->routeIs("admin.mobile-money.*") || request()->routeIs("admin.security.*") || request()->routeIs("admin.system-settings.*") || request()->routeIs("admin.languages.*") || request()->routeIs("admin.currencies.*"))
<a class="nav-link d-flex justify-content-between align-items-center {{ $settingsActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseSettings" role="button" aria-expanded="{{ $settingsActive ? "true" : "false" }}" aria-controls="collapseSettings">
    <span><i class="bi bi-sliders"></i> {{ __('app.settings') }}</span>
    <i class="bi bi-chevron-down chev"></i>
</a>
<div class="collapse {{ $settingsActive ? "show" : "" }}" id="collapseSettings">
    @php($systemActive = request()->routeIs("admin.system-settings.*") || request()->routeIs("admin.languages.*") || request()->routeIs("admin.currencies.*"))
    <a class="nav-link ms-3 d-flex justify-content-between align-items-center {{ $systemActive ? "" : "collapsed" }} nav-sub" data-bs-toggle="collapse" href="#collapseSystemSettings" role="button" aria-expanded="{{ $systemActive ? "true" : "false" }}" aria-controls="collapseSystemSettings">
        <span><i class="bi bi-gear"></i> {{ __('app.system_settings') }}</span>
        <i class="bi bi-chevron-down chev"></i>
    </a>
    <div class="collapse {{ $systemActive ? "show" : "" }}" id="collapseSystemSettings">
        <a href="{{ route("admin.system-settings.general") }}" class="nav-link ms-4 ps-4 {{ request()->routeIs("admin.system-settings.general*") ? "active" : "" }} nav-subsub">
            <i class="bi bi-building"></i> {{ __('app.system_settings_general') }}
        </a>
        @if(auth()->user()->isAdmin())
        <a href="{{ route("admin.system-settings.localization") }}" class="nav-link ms-4 ps-4 {{ request()->routeIs("admin.system-settings.localization*") ? "active" : "" }} nav-subsub">
            <i class="bi bi-globe2"></i> {{ __('app.system_settings_localization') }}
        </a>
        <a href="{{ route("admin.languages.index") }}" class="nav-link ms-4 ps-4 {{ request()->routeIs("admin.languages.*") ? "active" : "" }} nav-subsub">
            <i class="bi bi-translate"></i> {{ __('app.languages') }}
        </a>
        <a href="{{ route("admin.currencies.index") }}" class="nav-link ms-4 ps-4 {{ request()->routeIs("admin.currencies.*") ? "active" : "" }} nav-subsub">
            <i class="bi bi-currency-exchange"></i> {{ __('app.currencies') }}
        </a>
        @endif
    </div>
    @if(auth()->user()->isAdmin())
    <a href="{{ route("admin.landing-settings.index") }}" class="nav-link ms-3 {{ request()->routeIs("admin.landing-settings.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-house"></i> {{ __('app.landing_page_settings') }}
    </a>
    @endif
    <a href="{{ route("admin.business-settings.index") }}" class="nav-link ms-3 {{ request()->routeIs("admin.business-settings.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-percent"></i> {{ __('app.business_settings') }}
    </a>
    <a href="{{ route("admin.notifications.index") }}" class="nav-link ms-3 {{ request()->routeIs("admin.notifications.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-whatsapp"></i> {{ __('app.wa_notifications') }}
    </a>
    @if(auth()->user()->isAdmin())
    <a href="{{ route("admin.security.index") }}" class="nav-link ms-3 {{ request()->routeIs("admin.security.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-shield-lock"></i> {{ __('app.security_compliance') }}
    </a>
    <a href="{{ route("admin.mobile-money.edit") }}" class="nav-link ms-3 {{ request()->routeIs("admin.mobile-money.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-phone"></i> {{ __('app.mm_settings') }}
    </a>
    @endif
</div>
@if(auth()->user()->isAdmin())
<a href="{{ route("admin.testimonials.index") }}" class="nav-link {{ request()->routeIs("admin.testimonials.*") ? "active" : "" }}">
    <i class="bi bi-star"></i> {{ __('app.testimonials') }}
</a>
<a href="{{ route("admin.galleries.index") }}" class="nav-link {{ request()->routeIs("admin.galleries.*") ? "active" : "" }}">
    <i class="bi bi-images"></i> {{ __('app.gallery') }}
</a>
@endif
@endif
