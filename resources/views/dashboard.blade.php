@extends("layouts.admin")
@section("title", "Dashboard")
@section("page_title", "Dashboard")

@section("content")
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.total_customers') }}</div>
                    <div class="value">{{ $totalCustomers }}</div>
                </div>
                <div class="icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <i class="bi bi-person-plus"></i> {{ $newCustomers }} {{ __('app.new_this_month') }}
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.orders_today') }}</div>
                    <div class="value">{{ $todayOrders }}</div>
                </div>
                <div class="icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                @if($todayOrders > 0)
                    <i class="bi bi-check-circle text-success"></i> {{ __('app.active') }}
                @else
                    <i class="bi bi-dash-circle"></i> {{ __('app.none_yet') }}
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.in_progress') }}</div>
                    <div class="value">{{ $processingOrders }}</div>
                </div>
                <div class="icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <i class="bi bi-hourglass-split"></i> {{ $readyOrders }} {{ __('app.ready_for_pickup') }}
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.revenue_today') }}</div>
                    <div class="value text-1_4rem">{{ money($todayRevenue) }}</div>
                </div>
                <div class="icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                @if($isManager)
                    <i class="bi bi-graph-up"></i> {{ __('app.revenue_this_month') }} {{ money($monthRevenue) }}
                @else
                    <i class="bi bi-person-check"></i> {{ __('app.your_own_payments') }}
                @endif
            </div>
        </div>
    </div>
</div>

@if($storeReadiness)
    @include('partials.store-readiness', ['readiness' => $storeReadiness])
@endif

@if($storeKpis)
{{-- Management indicators of the store worked in (this month). --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-speedometer2 me-1"></i> {{ __('app.store_kpis_title') }}@if($kpiStore) · <span class="fw-semibold">{{ $kpiStore->name }}</span>@endif
            <span class="text-muted small">— {{ __('app.period_this_month') }} · {{ __('app.kpi_updated_at', ['time' => $kpiComputedAt]) }}</span></span>
        <a href="{{ route('management.index') }}" class="btn btn-sm btn-outline-primary">{{ __('app.management_indicators') }} <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="card-body">
        <div class="row g-2">
            @foreach($storeKpis as $kpi)
            <div class="col-6 col-md-3">
                <div class="border rounded p-2 h-100">
                    <div class="small text-muted">{{ __('app.kpi_' . $kpi['key']) }}</div>
                    <div class="fs-5 fw-bold">@include('management._value', ['value' => $kpi['value'], 'format' => $kpi['format']])</div>
                    @if($kpi['trend'])
                        @php($tColor = $kpi['trend']['good'] === null ? 'text-muted' : ($kpi['trend']['good'] ? 'text-success' : 'text-danger'))
                        <div class="small {{ $tColor }}">
                            <i class="bi bi-{{ $kpi['trend']['direction'] === 'up' ? 'arrow-up-right' : ($kpi['trend']['direction'] === 'down' ? 'arrow-down-right' : 'dash') }}"></i>{{ $kpi['change'] !== null ? abs($kpi['change']) . '%' : '' }}
                        </div>
                    @elseif($kpi['snapshot'] ?? false)
                        <div class="small text-muted">{{ __('app.kpi_now') }}</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

@if($resourceAlerts && array_sum($resourceAlerts) > 0)
<div class="row mb-1">
    @if($resourceAlerts["low_stock"] > 0)
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="{{ route("resources.items.index", ["kind" => "product", "low_stock" => 1]) }}" class="stat-card text-decoration-none d-block">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.low_stock_items') }}</div>
                    <div class="value text-danger">{{ $resourceAlerts["low_stock"] }}</div>
                </div>
                <div class="icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
        </a>
    </div>
    @endif
    @if($resourceAlerts["faulty"] > 0)
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="{{ route("resources.equipments.index", ["status" => "faulty"]) }}" class="stat-card text-decoration-none d-block">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.faulty_equipments') }}</div>
                    <div class="value text-warning">{{ $resourceAlerts["faulty"] }}</div>
                </div>
                <div class="icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-gear"></i>
                </div>
            </div>
        </a>
    </div>
    @endif
    @if($resourceAlerts["due_maintenance"] > 0)
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="{{ route("resources.maintenance.index", ["kind" => "preventive"]) }}" class="stat-card text-decoration-none d-block">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.maintenance_due') }}</div>
                    <div class="value text-info">{{ $resourceAlerts["due_maintenance"] }}</div>
                </div>
                <div class="icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-tools"></i>
                </div>
            </div>
        </a>
    </div>
    @endif
    @if($resourceAlerts["expiring_lots"] > 0)
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="{{ route("resources.lots.index", ["status" => "expiring"]) }}" class="stat-card text-decoration-none d-block">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.lots_expiring') }}</div>
                    <div class="value text-warning">{{ $resourceAlerts["expiring_lots"] }}</div>
                </div>
                <div class="icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-boxes"></i>
                </div>
            </div>
        </a>
    </div>
    @endif
    @if($resourceAlerts["expired_lots"] > 0)
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="{{ route("resources.lots.index", ["status" => "expired"]) }}" class="stat-card text-decoration-none d-block">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.lots_expired') }}</div>
                    <div class="value text-danger">{{ $resourceAlerts["expired_lots"] }}</div>
                </div>
                <div class="icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-calendar-x"></i>
                </div>
            </div>
        </a>
    </div>
    @endif
</div>
@endif

@if(($receivablesAlerts["total"] ?? 0) > 0)
<div class="row mb-1">
    <div class="col-xl-3 col-md-6 mb-3">
        <a href="{{ route("receivables.index") }}" class="stat-card text-decoration-none d-block">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.receivables') }}</div>
                    <div class="value text-1_4rem">{{ money($receivablesAlerts["total"]) }}</div>
                    <div class="mt-1 small {{ $receivablesAlerts["overdue"] > 0 ? "text-danger fw-semibold" : "text-muted" }}">
                        {{ $receivablesAlerts["overdue"] > 0
                            ? __('app.overdue_amount') . ": " . money($receivablesAlerts["overdue"]) . " (" . $receivablesAlerts["overdue_count"] . ")"
                            : __('app.no_overdue') }}
                    </div>
                </div>
                <div class="icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-cash-coin"></i>
                </div>
            </div>
        </a>
    </div>
</div>
@endif

<div class="row">
    @if($isManager)
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-graph-up me-1"></i> {{ __('app.weekly_revenue') }}</span>
                <a href="{{ route("reports") }}" class="btn btn-sm btn-outline-primary">{{ __('app.reports') }} <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <canvas id="weeklyChart" height="80"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
    @else
    <div class="col-12">
    @endif
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock me-1"></i> {{ __('app.latest_orders') }}</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($latestOrders as $o)
                    <a href="{{ route("orders.show", $o) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold small">{{ $o->order_number }}</span>
                            <span class="badge bg-{{ $o->status_color }} badge-status">{{ $o->status_label }}</span>
                        </div>
                        <div class="small text-muted">{{ $o->customer->name }} - {{ money($o->total_price) }}</div>
                    </a>
                    @empty
                    <div class="list-group-item text-muted text-center py-4">{{ __('app.no_orders') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push("scripts")
@if($isManager)
    <x-page-data name="dashboard" :data="['labels' => $chartLabels, 'values' => $chartData, 'texts' => ['revenue' => __('app.revenue')]]"/>
    @vite("resources/js/pages/dashboard.js")
@endif
@endpush
