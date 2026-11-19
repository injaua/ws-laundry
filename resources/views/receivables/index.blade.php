@extends("layouts.admin")
@section("title", __('app.receivables'))
@section("page_title", __('app.receivables'))

@section("content")
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="label text-muted small">{{ __('app.total_outstanding') }}</div>
                <div class="fs-4 fw-bold">{{ money($summary["total"]) }}</div>
                <div class="small text-muted">{{ $summary["count"] }} {{ __('app.open_credit_orders') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="label text-muted small">{{ __('app.overdue_amount') }}</div>
                <div class="fs-4 fw-bold {{ $summary["overdue"] > 0 ? "text-danger" : "text-success" }}">{{ money($summary["overdue"]) }}</div>
                <div class="small text-muted">{{ $summary["overdue_count"] }} {{ __('app.overdue_orders') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="label text-muted small">{{ __('app.due_next_week') }}</div>
                <div class="fs-4 fw-bold text-warning">{{ money($summary["due_next_week"]) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <a href="{{ route("receivables.export") }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-download"></i> Excel
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-hourglass-split me-1"></i> {{ __('app.aging_report') }}</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.customer') }}</th>
                        <th class="text-end">{{ __('app.credit_limit') }}</th>
                        <th class="text-end">{{ __('app.bucket_current') }}</th>
                        <th class="text-end">{{ __('app.bucket_1_30') }}</th>
                        <th class="text-end">{{ __('app.bucket_31_60') }}</th>
                        <th class="text-end">{{ __('app.bucket_61_90') }}</th>
                        <th class="text-end">{{ __('app.bucket_90_plus') }}</th>
                        <th class="text-end">{{ __('app.total_outstanding') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($aging as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row["customer"]?->name ?? "-" }}</td>
                        <td class="text-end small">{{ $row["credit_limit"] > 0 ? num($row["credit_limit"]) : __("app.unlimited") }}</td>
                        @foreach(\App\Services\AccountsReceivable::BUCKETS as $bucket)
                        <td class="text-end {{ $bucket !== "current" && $row["buckets"][$bucket] > 0 ? "text-danger fw-semibold" : "" }}">{{ $row["buckets"][$bucket] > 0 ? num($row["buckets"][$bucket]) : "-" }}</td>
                        @endforeach
                        <td class="text-end fw-bold">{{ num($row["total"]) }}</td>
                        <td class="text-center">
                            <a href="{{ route("receivables.customer", $row["customer"]) }}" class="btn btn-sm btn-outline-info" title="{{ __('app.customer_statement') }}">
                                <i class="bi bi-list-columns"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted"><i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>{{ __('app.no_receivables') }}</td>
                    </tr>
                    @endforelse
                </tbody>
                @if($aging->isNotEmpty())
                <tfoot>
                    <tr class="table-active">
                        <th>{{ __('app.total') }}</th>
                        <th></th>
                        @foreach(\App\Services\AccountsReceivable::BUCKETS as $bucket)
                        <th class="text-end">{{ num($summary["buckets"][$bucket]) }}</th>
                        @endforeach
                        <th class="text-end">{{ num($summary["total"]) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-receipt me-1"></i> {{ __('app.open_credit_orders') }}</span>
        <form action="{{ route("receivables.index") }}" method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ __('app.search') }}" value="{{ request("search") }}">
            <select name="customer_id" class="form-select form-select-sm">
                <option value="">{{ __('app.customer') }}</option>
                @foreach($creditCustomers as $c)
                    <option value="{{ $c->id }}" @selected(request("customer_id") == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm">
                <option value="">{{ __('app.status') }}</option>
                <option value="overdue" @selected(request("status") === "overdue")>{{ __('app.overdue_only') }}</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.order_number') }}</th>
                        <th>{{ __('app.customer') }}</th>
                        <th>{{ __('app.entry_date') }}</th>
                        <th>{{ __('app.due_date') }}</th>
                        <th class="text-end">{{ __('app.total') }}</th>
                        <th class="text-end">{{ __('app.paid') }}</th>
                        <th class="text-end">{{ __('app.outstanding_balance') }}</th>
                        <th class="text-center">{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td class="fw-semibold">{{ $order->order_number }}</td>
                        <td>{{ $order->customer?->name }}</td>
                        <td class="small">{{ $order->received_at?->format(date_fmt()) }}</td>
                        <td class="small {{ $order->isOverdue() ? "text-danger fw-semibold" : "" }}">{{ $order->due_date?->format(date_fmt()) ?? "-" }}</td>
                        <td class="text-end">{{ num($order->total_price) }}</td>
                        <td class="text-end text-success">{{ num($order->paidAmount()) }}</td>
                        <td class="text-end fw-bold">{{ num($order->remainingBalance()) }}</td>
                        <td class="text-center">
                            @if($order->isOverdue())
                                <span class="badge bg-danger">{{ __('app.days_overdue', ["days" => $order->daysOverdue()]) }}</span>
                            @else
                                <span class="badge bg-secondary">{{ $order->status_label }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route("orders.show", $order) }}" class="btn btn-sm btn-outline-info" title="{{ __('app.view') }}">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">{{ __('app.no_receivables') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
