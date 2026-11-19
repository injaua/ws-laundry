@extends("layouts.admin")
@section("title", __('app.profit_loss_report'))
@section("page_title", __('app.profit_loss_report'))

@section("content")
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("reports.profit-loss") }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">{{ __('app.from_date') }}</label>
                <input type="date" name="from" class="form-control" value="{{ $from->format("Y-m-d") }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('app.to_date') }}</label>
                <input type="date" name="to" class="form-control" value="{{ $to->format("Y-m-d") }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="bi bi-search"></i> {{ __('app.show') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small">{{ __('app.revenue') }}</div>
                <div class="fs-4 fw-bold text-success">{{ money($totalRevenue) }}</div>
                <small class="text-muted">{{ __('app.total_transactions', ['count' => $totalOrders]) }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small">{{ __('app.expenses') }}</div>
                <div class="fs-4 fw-bold text-danger">{{ money($totalExpenses) }}</div>
                <small class="text-muted">{{ __('app.total_count', ['count' => $expenses->count() ?? 0]) }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-4 {{ $netProfit >= 0 ? "border-primary" : "border-warning" }}">
            <div class="card-body">
                <div class="text-muted small">{{ __('app.net_profit_loss') }}</div>
                <div class="fs-4 fw-bold {{ $netProfit >= 0 ? "text-primary" : "text-warning" }}">
                    {{ money($netProfit) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-4 border-info">
            <div class="card-body">
                <div class="text-muted small">Margem</div>
                <div class="fs-4 fw-bold text-info">{{ $margin }}%</div>
                <small class="text-muted">{{ $netProfit >= 0 ? __('app.net_profit') : __('app.net_loss') }}</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart-line"></i> {{ __('app.revenue') }} vs {{ __('app.expenses') }}</div>
            <div class="card-body">
                <canvas id="profitChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-pie-chart"></i> {{ __('app.category') }} ({{ __('app.expenses') }})</div>
            <div class="card-body">
                <canvas id="expensePieChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

@push("scripts")
    <x-page-data name="profit-loss-report" :data="[
        'labels' => $chartLabels, 'revenue' => $chartRevenue, 'expenses' => $chartExpense,
        'categories' => ['labels' => $expCatLabels, 'values' => $expCatValues],
        'texts' => ['revenue' => __('app.revenue'), 'expenses' => __('app.expenses')],
    ]"/>
    @vite("resources/js/pages/profit-loss-report.js")
@endpush
@endsection
