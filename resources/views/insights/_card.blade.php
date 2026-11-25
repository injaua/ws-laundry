{{-- One insight: alert or briefing, with its actions. --}}
@php($closed = in_array($insight->status, ['resolved', 'dismissed'], true))
<div class="card mb-3 ai-insight sev-{{ $insight->severity }} kind-{{ $insight->kind }} {{ $closed ? 'is-closed' : '' }}">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <div>
                @if($insight->kind === 'briefing')
                    <span class="badge text-bg-primary"><i class="bi bi-calendar-check"></i> {{ __('app.ai_kind_briefing') }}</span>
                @else
                    <span class="badge text-bg-{{ $insight->severityColor() }}"><i class="bi {{ $insight->severityIcon() }}"></i> {{ __('app.ai_severity_' . $insight->severity) }}</span>
                @endif
                <span class="badge text-bg-light">{{ $insight->audience === 'company' ? __('app.ai_audience_company') : ($insight->store?->name ?? '') }}</span>
                @if($insight->status === 'new')<span class="badge text-bg-success">{{ __('app.ai_status_new') }}</span>@endif
                @if($closed)<span class="badge text-bg-secondary">{{ __('app.ai_status_' . $insight->status) }}</span>@endif
            </div>
            <div class="ai-muted">
                {{ \App\Support\SystemSettings::formatDate($insight->period_date) }}
                @if($insight->ai_written) · <i class="bi bi-stars"></i> {{ __('app.ai_written_by_ai') }}@endif
            </div>
        </div>

        <h6 class="fw-semibold mb-2">{{ $insight->title }}</h6>
        <div class="ai-insight-body small">{!! $insight->bodyHtml() !!}</div>
        @if($insight->recommendation)
            <div class="ai-recommendation small mt-2"><i class="bi bi-arrow-right-circle"></i> {{ $insight->recommendation }}</div>
        @endif

        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
            @if($insight->link)
                <a href="{{ $insight->link }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> {{ __('app.ai_open_page') }}</a>
            @endif
            @can('use-assistant')
                <a href="{{ route('assistant.index', ['q' => __('app.ai_ask_about', ['title' => $insight->title])]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-stars"></i> {{ __('app.ai_ask_assistant') }}</a>
            @endcan
            @unless($closed)
                @if($insight->kind === 'alert')
                    <form method="POST" action="{{ route('insights.status', $insight) }}">
                        @csrf <input type="hidden" name="status" value="resolved">
                        <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2"></i> {{ __('app.ai_resolve') }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('insights.status', $insight) }}">
                    @csrf <input type="hidden" name="status" value="dismissed">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i> {{ __('app.ai_dismiss') }}</button>
                </form>
            @endunless
            <span class="ms-auto ai-muted">{{ __('app.ai_was_useful') }}</span>
            @foreach([1 => 'bi-hand-thumbs-up', 0 => 'bi-hand-thumbs-down'] as $value => $icon)
                <form method="POST" action="{{ route('insights.feedback', $insight) }}">
                    @csrf <input type="hidden" name="useful" value="{{ $value }}">
                    <button class="btn btn-sm {{ $useful !== null && (int) $useful === $value ? 'btn-secondary' : 'btn-outline-secondary' }}" title="{{ __($value ? 'app.ai_useful' : 'app.ai_not_useful') }}"><i class="bi {{ $icon }}"></i></button>
                </form>
            @endforeach
        </div>
    </div>
</div>
