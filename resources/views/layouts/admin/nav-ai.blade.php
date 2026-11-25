{{-- Sidebar · AI assistant (every role) and automatic insights (admin / owner). --}}
@if(auth()->user()->can("use-assistant") || auth()->user()->can("receive-insights"))
<div class="nav-section mt-3">{{ __('app.ai_section') }}</div>
@can("use-assistant")
<a href="{{ route("assistant.index") }}" class="nav-link {{ request()->routeIs("assistant.*") ? "active" : "" }}">
    <i class="bi bi-stars"></i> {{ __('app.ai_assistant') }}
</a>
@endcan
@can("receive-insights")
<a href="{{ route("insights.index") }}" class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs("insights.*") ? "active" : "" }}">
    <span><i class="bi bi-lightbulb"></i> {{ __('app.ai_insights') }}</span>
    @if($navBadges['insights'] > 0)<span class="badge rounded-pill {{ $navBadges['insights_critical'] ? "bg-danger" : "bg-primary" }}">{{ $navBadges['insights'] }}</span>@endif
</a>
@endcan
@endif
