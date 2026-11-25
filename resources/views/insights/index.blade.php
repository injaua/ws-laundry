@extends("layouts.admin")
@section("title", __('app.ai_insights'))
@section("page_title", __('app.ai_insights'))
@section("page_subtitle", __('app.ai_insights_subtitle'))

@push("styles")
    @vite("resources/css/pages/assistant.css")
@endpush

@section("content")
<div class="ai-page">
    {{-- Switch and actions --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1">
                <div class="fw-semibold">
                    @if($enabled)
                        <i class="bi bi-bell-fill text-success"></i> {{ __('app.ai_insights_receiving') }}
                    @else
                        <i class="bi bi-bell-slash text-muted"></i> {{ __('app.ai_insights_not_receiving') }}
                    @endif
                </div>
                <div class="small text-muted">{{ auth()->user()->isAdmin() ? __('app.ai_insights_admin_help') : __('app.ai_insights_owner_help') }}</div>
            </div>
            <form method="POST" action="{{ route('insights.toggle') }}">
                @csrf
                <button class="btn btn-sm {{ $enabled ? 'btn-outline-secondary' : 'btn-success' }}">
                    <i class="bi {{ $enabled ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                    {{ $enabled ? __('app.ai_insights_turn_off') : __('app.ai_insights_turn_on') }}
                </button>
            </form>
            @if($enabled)
                <form method="POST" action="{{ route('insights.generate') }}">
                    @csrf
                    <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat"></i> {{ __('app.ai_insights_generate') }}</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach(\App\Http\Controllers\InsightController::FILTERS as $f)
            <a href="{{ route('insights.index', ['filter' => $f, 'severity' => $severity]) }}" class="btn btn-sm {{ $filter === $f ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('app.ai_filter_' . $f) }}
                @if($f === 'open' && $counts['open'])<span class="badge bg-light text-dark ms-1">{{ $counts['open'] }}</span>@endif
            </a>
        @endforeach
        <span class="vr mx-1"></span>
        @foreach(\App\Models\AiInsight::SEVERITIES as $s)
            <a href="{{ route('insights.index', ['filter' => $filter, 'severity' => $severity === $s ? null : $s]) }}" class="btn btn-sm {{ $severity === $s ? 'btn-' . ($s === 'critical' ? 'danger' : ($s === 'warning' ? 'warning' : 'info')) : 'btn-outline-secondary' }}">
                {{ __('app.ai_severity_' . $s) }}
            </a>
        @endforeach
    </div>

    @forelse($insights as $insight)
        @include("insights._card", ["insight" => $insight, "useful" => $feedback[$insight->id] ?? null])
    @empty
        <div class="card"><div class="card-body text-center text-muted py-5">
            <i class="bi bi-lightbulb fs-1 d-block mb-2"></i>
            {{ $enabled ? __('app.ai_insights_empty') : __('app.ai_insights_empty_off') }}
        </div></div>
    @endforelse

    {{ $insights->links() }}
</div>
@endsection
