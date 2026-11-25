{{-- Back-office sidebar. Badges come from App\View\Composers\AdminNavigationComposer ($navBadges). --}}
<nav class="sidebar" id="sidebar">
    <button type="button" class="btn-close sidebar-close d-md-none" id="sidebarClose" aria-label="{{ __('app.close') }}"></button>
    <div class="sidebar-brand">
        <img src="{{ \App\Support\SystemSettings::logoUrl() }}" alt="Logo" class="sidebar-logo">
        <div class="sidebar-company">{{ \App\Support\SystemSettings::companyName() }}</div>
    </div>
    @if(auth()->user()->isCourier())
    {{-- Couriers only work their route. --}}
    <div class="nav-section">{{ __('app.main_menu') }}</div>
    <a href="{{ route("courier.route") }}" class="nav-link {{ request()->routeIs("courier.route") ? "active" : "" }}">
        <i class="bi bi-signpost-split"></i> {{ __('app.my_route') }}
    </a>
    @else
        @include("layouts.admin.nav-operations")
        @include("layouts.admin.nav-finance")
        @include("layouts.admin.nav-messages")
        @include("layouts.admin.nav-hr")
        @include("layouts.admin.nav-settings")
    @endif
        @include("layouts.admin.nav-ai")
        @include("layouts.admin.nav-account")
</nav>
