@extends("layouts.admin")
@section("title", __('app.reports'))
@section("page_title", __('app.reports_title'))

@section("content")
{{-- Filter --}}
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("reports") }}" method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">{{ __('app.from_date') }}</label>
                <input type="date" name="from" class="form-control" value="{{ request("from", $from) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('app.to_date') }}</label>
                <input type="date" name="to" class="form-control" value="{{ request("to", $to) }}">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-funnel"></i> {{ __('app.show') }}</button>
                <a href="{{ route("reports") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
            </div>
        </form>
    </div>
</div>

{{-- Statistics --}}
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.total_orders') }}</div>
                    <div class="value">{{ $totalOrders }}</div>
                </div>
                <div class="icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.total_revenue') }}</div>
                    <div class="value" style="font-size:1.4rem">{{ number_format($totalRevenue, 0, ".", ",") }} Mts</div>
                </div>
                <div class="icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">{{ __('app.avg_per_order') }}</div>
                    <div class="value" style="font-size:1.4rem">{{ number_format($avgOrder, 0, ".", ",") }} Mts</div>
                </div>
                <div class="icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Chart --}}
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-bar-chart me-1"></i> {{ __('app.revenue') }}</div>
    <div class="card-body">
        <canvas id="revenueChart" height="100"></canvas>
    </div>
</div>

{{-- Detail table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-table me-1"></i> {{ __('app.transactions') }}</span>
        <span class="text-muted small">{{ __('app.total_transactions', ['count' => count($orders)]) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.date') }}</th>
                        <th>{{ __('app.order_number') }}</th>
                        <th>{{ __('app.customer') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th class="text-end">{{ __('app.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                    <tr>
                        <td class="small">{{ $o->received_at->format("d/m/Y") }}</td>
                        <td class="fw-semibold">{{ $o->order_number }}</td>
                        <td>{{ $o->customer->name }}</td>
                        <td><span class="badge bg-{{ $o->status_color }}">{{ $o->status_label }}</span></td>
                        <td class="fw-semibold text-end">{{ number_format($o->total_price, 0, ".", ",") }} Mts</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-4">{{ __('app.no_transactions') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push("scripts")
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById("revenueChart").getContext("2d");
const isDark = document.documentElement.getAttribute("data-bs-theme") === "dark";
Chart.defaults.color = isDark ? "#adb5bd" : "#6c757d";
Chart.defaults.borderColor = isDark ? "rgba(255,255,255,0.1)" : "rgba(0,0,0,0.05)";
new Chart(ctx, {
    type: "line",
    data: {
        labels: @json($chartLabels),
        datasets: [{
            label: "{{ __('app.revenue') }}",
            data: @json($chartData),
            borderColor: isDark ? "#7986cb" : "#1a237e",
            backgroundColor: isDark ? "rgba(121, 134, 203, 0.15)" : "rgba(26, 35, 126, 0.1)",
            fill: true,
            tension: 0.4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => v.toLocaleString("en-US") + " Mts" }
            }
        }
    }
});
</script>
@endpush
