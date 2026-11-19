{{-- Sidebar · Profile, activity log, logout. --}}
<div class="nav-section mt-3">{{ __('app.account') }}</div>
<a href="{{ route("profile.edit") }}" class="nav-link">
    <i class="bi bi-person"></i> {{ __('app.profile') }}
</a>
@if(auth()->user()->can("manage-store"))
<a href="{{ route("activity-logs.index") }}" class="nav-link {{ request()->routeIs("activity-logs.*") ? "active" : "" }}">
    <i class="bi bi-clock-history"></i> {{ __('app.activity_log') }}
</a>
@endif
<a href="{{ route("help.index") }}" class="nav-link {{ request()->routeIs("help.*") ? "active" : "" }}">
    <i class="bi bi-book"></i> {{ __('app.help') }}
</a>
<form method="POST" action="{{ route("logout") }}" class="d-inline">
    @csrf
    <button type="submit" class="nav-link w-100 text-start nav-logout">
        <i class="bi bi-box-arrow-right"></i> {{ __('app.logout') }}
    </button>
</form>
<div class="mt-auto p-3 sidebar-footer">
    <small>&copy; {{ date("Y") }} {{ \App\Support\SystemSettings::legalName() }}</small>
</div>
