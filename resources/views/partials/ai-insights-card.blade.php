{{-- Dashboard: the latest open insights of the user (admin / owner with insights switched on). --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-lightbulb me-1"></i> {{ __('app.ai_insights') }}</span>
        <a href="{{ route('insights.index') }}" class="btn btn-sm btn-outline-primary">{{ __('app.ai_see_all') }} <i class="bi bi-arrow-right"></i></a>
    </div>
    <ul class="list-group list-group-flush">
        @foreach($insights as $insight)
            <li class="list-group-item d-flex gap-2 align-items-start">
                <i class="bi {{ $insight->kind === 'briefing' ? 'bi-calendar-check text-primary' : $insight->severityIcon() . ' text-' . $insight->severityColor() }} mt-1"></i>
                <div class="flex-grow-1">
                    <a href="{{ route('insights.index') }}" class="fw-semibold text-reset text-decoration-none">{{ $insight->title }}</a>
                    @if($insight->recommendation)<div class="small text-muted">{{ $insight->recommendation }}</div>@endif
                </div>
                <span class="small text-muted text-nowrap">{{ \App\Support\SystemSettings::formatDate($insight->period_date) }}</span>
            </li>
        @endforeach
    </ul>
</div>
