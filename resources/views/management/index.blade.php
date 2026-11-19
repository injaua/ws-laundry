@extends("layouts.admin")
@section("title", __('app.management_indicators'))
@section("page_title", __('app.management_indicators'))
@section("page_subtitle", $data['period']['from']->format(date_fmt()) . ' – ' . $data['period']['to']->format(date_fmt()) . ' · ' . __('app.compared_with_previous', ['days' => $data['period']['days']]))

@php($groupIcons = ["finance" => "cash-stack", "sales" => "people", "operations" => "kanban", "people" => "person-badge", "costs" => "box-seam"])
@php($statusColors = ["good" => "success", "warning" => "warning", "bad" => "danger"])
@php($byGroup = collect($data['indicators'])->groupBy('group'))
@php($b = $data['breakdowns'])

@section("content")
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-center" id="period-form">
        <select name="period" id="period" class="form-select mw-200px">
            @foreach($presets as $p)
                <option value="{{ $p }}" @selected($preset === $p)>{{ __('app.period_' . $p) }}</option>
            @endforeach
        </select>
        <span id="custom-range" class="d-flex gap-2 {{ $preset === 'custom' ? '' : 'd-none' }}">
            <input type="date" name="from" value="{{ $data['period']['from']->toDateString() }}" class="form-control">
            <input type="date" name="to" value="{{ $data['period']['to']->toDateString() }}" class="form-control">
            <button class="btn btn-primary"><i class="bi bi-funnel"></i></button>
        </span>
    </form>
    <div class="d-flex gap-2">
        @if(auth()->user()->isManager())
        <a href="{{ route('management.targets') }}" class="btn btn-outline-secondary"><i class="bi bi-bullseye"></i> {{ __('app.kpi_targets') }}</a>
        @endif
        <a href="{{ route('management.export', request()->query()) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv"></i> CSV</a>
    </div>
</div>

@foreach(\App\Services\ManagementIndicators::GROUPS as $group)
    <h6 class="text-uppercase text-muted small fw-semibold mt-3 mb-2"><i class="bi bi-{{ $groupIcons[$group] }} me-1"></i> {{ __('app.kpi_group_' . $group) }}</h6>
    <div class="row g-2">
        @foreach($byGroup[$group] ?? [] as $kpi)
            <div class="col-6 col-md-4 col-xl-3">
                <div class="card h-100 {{ $kpi['status'] ? 'border-' . $statusColors[$kpi['status']] : '' }}">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="small text-muted" title="{{ __('app.kpi_' . $kpi['key'] . '_help') }}">{{ __('app.kpi_' . $kpi['key']) }} <i class="bi bi-info-circle opacity-50"></i></span>
                            @if($kpi['status'])
                                <span class="badge bg-{{ $statusColors[$kpi['status']] }}" title="{{ __('app.kpi_status_' . $kpi['status']) }}">&nbsp;</span>
                            @endif
                        </div>
                        <div class="fs-5 fw-bold">@include('management._value', ['value' => $kpi['value'], 'format' => $kpi['format']])</div>
                        <div class="small d-flex justify-content-between gap-2">
                            <span>
                                @if($kpi['trend'])
                                    @php($tColor = $kpi['trend']['good'] === null ? 'text-muted' : ($kpi['trend']['good'] ? 'text-success' : 'text-danger'))
                                    <span class="{{ $tColor }}">
                                        <i class="bi bi-{{ $kpi['trend']['direction'] === 'up' ? 'arrow-up-right' : ($kpi['trend']['direction'] === 'down' ? 'arrow-down-right' : 'dash') }}"></i>{{ $kpi['change'] !== null ? abs($kpi['change']) . '%' : '' }}
                                    </span>
                                    <span class="text-muted">· @include('management._value', ['value' => $kpi['previous'], 'format' => $kpi['format']])</span>
                                @elseif($kpi['snapshot'] ?? false)
                                    <span class="text-muted">{{ __('app.kpi_now') }}</span>
                                @endif
                            </span>
                            @if($kpi['target'] !== null)
                                <span class="text-muted text-nowrap" title="{{ __('app.target') }}"><i class="bi bi-bullseye"></i> @include('management._value', ['value' => $kpi['target'], 'format' => $kpi['format']])</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endforeach

<div class="row g-3 mt-1">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">{{ __('app.trend_12_months') }}</div>
            <div class="card-body"><canvas id="trendChart" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">{{ __('app.customers_and_punctuality') }}</div>
            <div class="card-body"><canvas id="opsChart" height="220"></canvas></div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">{{ __('app.revenue_by_type') }}</div>
            <ul class="list-group list-group-flush">
                @php($typeTotal = array_sum($b['revenue_by_type']))
                @forelse($b['revenue_by_type'] as $type => $amount)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between small"><span>{{ __('app.revenue_type_' . $type) }}</span><span>{{ money($amount) }}</span></div>
                        <div class="progress h-5px"><div class="progress-bar" style="width: {{ $typeTotal > 0 ? round($amount / $typeTotal * 100) : 0 }}%"></div></div>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">{{ __('app.no_data') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">{{ __('app.top_customers') }}</div>
            <ul class="list-group list-group-flush">
                @forelse($b['top_customers'] as $row)
                    <li class="list-group-item d-flex justify-content-between small">
                        <a href="{{ route('customers.show', $row['customer_id']) }}" class="text-truncate">{{ $row['name'] }}</a>
                        <span class="text-nowrap">{{ money($row['revenue']) }} <span class="text-muted">({{ $row['share'] ?? 0 }}%)</span></span>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">{{ __('app.no_data') }}</li>
                @endforelse
            </ul>
            @if($b['top_customers']->isNotEmpty() && ($b['top_customers']->first()['share'] ?? 0) >= 30)
                <div class="card-footer small text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> {{ __('app.customer_concentration_warning') }}</div>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">{{ __('app.expenses_by_category') }}</div>
            <ul class="list-group list-group-flush">
                @forelse($b['expenses_by_category'] as $row)
                    <li class="list-group-item d-flex justify-content-between small"><span>{{ $row['name'] }}</span><span>{{ money($row['amount']) }}</span></li>
                @empty
                    <li class="list-group-item text-muted small">{{ __('app.no_data') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> {{ __('app.kpi_footnote') }}</p>
@endsection

@push("scripts")
    <x-page-data name="management" :data="[
        'trend' => $trend,
        'texts' => [
            'revenue' => __('app.kpi_revenue'), 'expenses' => __('app.kpi_expenses'), 'result' => __('app.kpi_operating_result'),
            'customers' => __('app.kpi_active_customers'), 'onTime' => __('app.kpi_on_time_rate'),
        ],
    ]"/>
    @vite("resources/js/pages/management.js")
@endpush
