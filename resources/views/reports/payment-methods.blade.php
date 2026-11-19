@extends("layouts.admin")
@section("title", __('app.payment_methods_report'))
@section("page_title", __('app.payment_methods_report'))

@section("content")
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("reports.payment-methods") }}" method="GET" class="row g-2 align-items-end">
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
    <div class="col-md-4">
        <div class="card border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small">{{ __('app.total_received') }}</div>
                <div class="fs-4 fw-bold text-success">{{ money($totalReceived) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-muted small">{{ __('app.payments') }}</div>
                <div class="fs-4 fw-bold text-primary">{{ $totalCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start border-4 border-info">
            <div class="card-body">
                <div class="text-muted small">{{ __('app.method') }}</div>
                <div class="fs-4 fw-bold text-info">{{ count($rows) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-credit-card me-1"></i> {{ __('app.payment_methods_report') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.method') }}</th>
                                <th>{{ __('app.payments_count') }}</th>
                                <th class="text-end">{{ __('app.total') }}</th>
                                <th class="text-end">{{ __('app.share') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                            <tr>
                                <td class="fw-medium">{{ __('app.' . $row["method"]) }}</td>
                                <td>{{ $row["count"] }}</td>
                                <td class="text-end fw-semibold">{{ money($row["amount"]) }}</td>
                                <td class="text-end">{{ $row["share"] }}%</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="bi bi-cash-coin fs-1 d-block mb-2"></i>
                                    {{ __('app.no_transactions') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if(count($rows) > 0)
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td>{{ __('app.total') }}</td>
                                <td>{{ $totalCount }}</td>
                                <td class="text-end">{{ money($totalReceived) }}</td>
                                <td class="text-end">100%</td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-pie-chart"></i> {{ __('app.payment_methods_report') }}</div>
            <div class="card-body">
                @if(count($rows) > 0)
                <canvas id="methodPieChart" height="220"></canvas>
                @else
                <div class="text-center text-muted py-5">{{ __('app.no_transactions') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@if(count($rows) > 0)
@push("scripts")
    <x-page-data name="payment-methods-report" :data="['labels' => $pieLabels, 'values' => $pieValues]"/>
    @vite("resources/js/pages/payment-methods-report.js")
@endpush
@endif
@endsection
