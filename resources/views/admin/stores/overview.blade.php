@extends("layouts.admin")
@section("title", __('app.stores_overview'))
@section("page_title", __('app.stores_overview'))

@section("content")
<form method="GET" class="card mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-sm-4 col-md-3">
            <label class="form-label small">{{ __('app.from') }}</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
        </div>
        <div class="col-sm-4 col-md-3">
            <label class="form-label small">{{ __('app.to') }}</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary"><i class="bi bi-funnel"></i> {{ __('app.filter') }}</button>
        </div>
        <div class="col-12 col-lg text-lg-end small text-muted">{{ __('app.stores_overview_help') }}</div>
    </div>
</form>

<div class="row g-3 mb-3">
    @foreach(["sales" => "bi-receipt", "received" => "bi-cash-coin", "expenses" => "bi-wallet2", "result" => "bi-graph-up-arrow"] as $metric => $icon)
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted"><i class="bi {{ $icon }}"></i> {{ __('app.overview_' . $metric) }} · {{ __('app.stores_all') }}</div>
                    <div class="fs-4 fw-semibold {{ $metric === "result" && $totals[$metric] < 0 ? "text-danger" : "" }}">{{ money($totals[$metric]) }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-shop-window me-1"></i> {{ __('app.stores_by_store') }}</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.store') }}</th>
                        <th class="text-end">{{ __('app.overview_orders') }}</th>
                        <th class="text-end">{{ __('app.overview_sales') }}</th>
                        <th class="text-end">{{ __('app.overview_received') }}</th>
                        <th class="text-end">{{ __('app.overview_refunds') }}</th>
                        <th class="text-end">{{ __('app.overview_expenses') }}</th>
                        <th class="text-end">{{ __('app.overview_result') }}</th>
                        <th class="text-end">{{ __('app.overview_receivables') }}</th>
                        <th class="text-end">{{ __('app.overview_late_orders') }}</th>
                        <th class="text-end">{{ __('app.overview_customers') }}</th>
                        <th class="text-end">{{ __('app.overview_staff') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stores as $row)
                        <tr>
                            <td class="fw-medium text-nowrap">
                                {{ $row["store"]->label() }}
                                @unless($row["store"]->is_active)
                                    <span class="badge bg-secondary ms-1">{{ __('app.inactive') }}</span>
                                @endunless
                            </td>
                            <td class="text-end">{{ num($row["orders"]) }}</td>
                            <td class="text-end">{{ money($row["sales"]) }}</td>
                            <td class="text-end">{{ money($row["received"]) }}</td>
                            <td class="text-end">{{ money($row["refunds"]) }}</td>
                            <td class="text-end">{{ money($row["expenses"]) }}</td>
                            <td class="text-end fw-semibold {{ $row["result"] < 0 ? "text-danger" : "text-success" }}">{{ money($row["result"]) }}</td>
                            <td class="text-end">{{ money($row["receivables"]) }}</td>
                            <td class="text-end">{{ num($row["late_orders"]) }}</td>
                            <td class="text-end">{{ num($row["customers"]) }} <span class="small text-muted">(+{{ num($row["new_customers"]) }})</span></td>
                            <td class="text-end">{{ num($row["staff"]) }}</td>
                            <td class="text-end">
                                @if($row["store"]->is_active)
                                    <form action="{{ route("admin.stores.switch", $row["store"]) }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="to" value="reports">
                                        <button class="btn btn-sm btn-outline-primary text-nowrap" title="{{ __('app.store_open_reports') }}"><i class="bi bi-graph-up"></i> {{ __('app.reports') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td>{{ __('app.total') }}</td>
                        <td class="text-end">{{ num($totals["orders"]) }}</td>
                        <td class="text-end">{{ money($totals["sales"]) }}</td>
                        <td class="text-end">{{ money($totals["received"]) }}</td>
                        <td class="text-end">{{ money($totals["refunds"]) }}</td>
                        <td class="text-end">{{ money($totals["expenses"]) }}</td>
                        <td class="text-end {{ $totals["result"] < 0 ? "text-danger" : "" }}">{{ money($totals["result"]) }}</td>
                        <td class="text-end">{{ money($totals["receivables"]) }}</td>
                        <td class="text-end">{{ num($totals["late_orders"]) }}</td>
                        <td class="text-end">{{ num($totals["customers"]) }} <span class="small text-muted">(+{{ num($totals["new_customers"]) }})</span></td>
                        <td class="text-end">{{ num($totals["staff"]) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
