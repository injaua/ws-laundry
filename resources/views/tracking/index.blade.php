@extends("layouts.admin")
@section("title", __('app.piece_tracking'))
@section("page_title", __('app.piece_tracking'))

@push("styles")
    @vite("resources/css/pages/tracking.css")
@endpush

@section("content")
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-2 small text-muted">{{ __('app.scan_choose_stage') }}</div>
                <div class="stage-btns d-flex flex-wrap gap-2 mb-3" role="group" aria-label="{{ __('app.scan_choose_stage') }}">
                    @foreach($stages as $stage)
                        <button type="button" class="btn btn-outline-primary" data-stage="{{ $stage }}">{{ __('app.status_' . $stage) }}</button>
                    @endforeach
                    <button type="button" class="btn btn-outline-secondary" data-stage="lookup"><i class="bi bi-search"></i> {{ __('app.scan_lookup') }}</button>
                </div>

                <form id="scanForm" autocomplete="off">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text d-none d-sm-flex"><i class="bi bi-upc-scan"></i></span>
                        <input type="text" id="scanCode" class="form-control scan-input" placeholder="{{ __('app.scan_placeholder') }}" autofocus maxlength="60" aria-label="{{ __('app.scan_placeholder') }}">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg d-sm-none"></i><span class="d-none d-sm-inline">{{ __('app.scan_submit') }}</span><span class="visually-hidden d-sm-none">{{ __('app.scan_submit') }}</span></button>
                        <button class="btn btn-outline-secondary d-none" type="button" id="cameraBtn" title="{{ __('app.scan_camera') }}"><i class="bi bi-camera"></i></button>
                    </div>
                    <div class="form-text">{{ __('app.scan_help') }}</div>
                </form>
                <video id="scanVideo" class="mt-2 d-none" playsinline muted></video>
            </div>
        </div>

        <div id="scanResult" class="card scan-result mb-3">
            <div class="card-body text-muted text-center d-flex align-items-center justify-content-center">
                <span><i class="bi bi-upc-scan fs-3 d-block mb-1"></i> {{ __('app.scan_waiting') }}</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i> {{ __('app.scan_recent') }}</div>
            <ul class="list-group list-group-flush recent" id="recent">
                <li class="list-group-item text-muted small" id="recentEmpty">{{ __('app.scan_recent_empty') }}</li>
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> {{ __('app.pieces_in_progress') }}</div>
            <ul class="list-group list-group-flush">
                @forelse(collect($stages)->filter(fn ($s) => isset($inProgress[$s])) as $stage)
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.status_' . $stage) }}</span><span class="fw-semibold">{{ $inProgress[$stage] }}</span></li>
                @empty
                    <li class="list-group-item text-muted small">{{ __('app.no_pieces_in_progress') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-calendar-check me-1"></i> {{ __('app.pieces_moved_today') }}</div>
            <ul class="list-group list-group-flush">
                @forelse(collect($stages)->filter(fn ($s) => isset($todayByStage[$s])) as $stage)
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.status_' . $stage) }}</span><span class="fw-semibold">{{ $todayByStage[$stage] }}</span></li>
                @empty
                    <li class="list-group-item text-muted small">{{ __('app.no_scans_today') }}</li>
                @endforelse
            </ul>
        </div>

        @if($withIssues->isNotEmpty())
        <div class="card border-warning">
            <div class="card-header text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i> {{ __('app.pieces_with_issues') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($withIssues as $p)
                    <li class="list-group-item small">
                        <a href="{{ route("orders.show", $p->order_id) }}#pieces" class="fw-semibold font-monospace">{{ $p->code }}</a>
                        — {{ $p->issue }}
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection

@push("scripts")
    <x-page-data name="tracking" :data="[
        'urls' => ['scan' => route('tracking.scan'), 'lookup' => route('tracking.lookup')],
        'texts' => [
            'ok' => __('app.scan_ok'), 'error' => __('app.scan_error'), 'order' => __('app.order'),
            'readyOf' => __('app.pieces_ready_of'), 'open' => __('app.view_order'), 'history' => __('app.piece_history'),
            'intake' => __('app.piece_intake_notes'), 'issue' => __('app.piece_issue'), 'chooseStage' => __('app.scan_choose_stage'),
            'network' => __('app.scan_network_error'),
        ],
    ]"/>
    @vite("resources/js/pages/tracking.js")
@endpush
