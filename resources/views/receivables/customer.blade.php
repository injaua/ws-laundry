@extends("layouts.admin")
@section("title", __('app.customer_statement') . " — " . $customer->name)
@section("page_title", __('app.customer_statement') . " — " . $customer->name)

@section("content")
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted">{{ __('app.credit_limit') }}</div>
                <div class="fs-4 fw-bold">{{ $customer->credit_limit > 0 ? money($customer->credit_limit) : __('app.unlimited') }}</div>
                <div class="small text-muted">{{ __('app.credit_terms_days') }}: {{ $customer->credit_terms_days }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted">{{ __('app.total_outstanding') }}</div>
                <div class="fs-4 fw-bold {{ $summary["total"] > 0 ? "" : "text-success" }}">{{ money($summary["total"]) }}</div>
                <div class="small text-muted">{{ $summary["count"] }} {{ __('app.open_credit_orders') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted">{{ __('app.overdue_amount') }}</div>
                <div class="fs-4 fw-bold {{ $summary["overdue"] > 0 ? "text-danger" : "text-success" }}">{{ money($summary["overdue"]) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 d-flex flex-column gap-2 justify-content-end">
        <a href="{{ route("customers.statement", $customer) }}" class="btn btn-sm btn-outline-primary" target="_blank">
            <i class="bi bi-printer"></i> {{ __('app.statement_of_account') }}
        </a>
        <a href="{{ route("invoices.create", ["customer_id" => $customer->id]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-file-earmark-plus"></i> {{ __('app.new_invoice') }}
        </a>
        <a href="{{ route("customers.show", $customer) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> {{ __('app.back') }}
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        @include("payments._grouped-form", [
            "action" => route("customers.payments.store", $customer),
            "outstanding" => $payable,
            "hint" => __('app.grouped_payment_hint'),
            "customer" => $customer,
        ])
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-files me-1"></i> {{ __('app.invoices') }}</span>
                <span class="small text-muted">{{ __('app.billing_cycle_' . $customer->billing_cycle) }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <tbody>
                            @forelse($invoices as $invoice)
                            <tr>
                                <td class="fw-semibold"><a href="{{ route("invoices.show", $invoice) }}">{{ $invoice->label() }}</a></td>
                                <td class="small">{{ $invoice->period_start->format("d/m") }}–{{ $invoice->period_end->format(date_fmt()) }}</td>
                                <td class="small">{{ $invoice->due_date?->format(date_fmt()) ?? "-" }}</td>
                                <td class="text-end">{{ num($invoice->total) }}</td>
                                <td class="text-end fw-semibold">{{ $invoice->isVoid() ? "-" : num($invoice->balance()) }}</td>
                                <td class="text-center"><span class="badge bg-{{ $invoice->statusColor() }}">{{ __('app.invoice_status_' . $invoice->displayStatus()) }}</span></td>
                            </tr>
                            @empty
                            <tr><td class="text-center text-muted py-4">{{ __('app.no_invoices') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-list-columns me-1"></i> {{ __('app.credit_orders') }}</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.order_number') }}</th>
                        <th>{{ __('app.entry_date') }}</th>
                        <th>{{ __('app.completion_date') }}</th>
                        <th>{{ __('app.due_date') }}</th>
                        <th>{{ __('app.invoice') }}</th>
                        <th class="text-end">{{ __('app.total') }}</th>
                        <th class="text-end">{{ __('app.paid') }}</th>
                        <th class="text-end">{{ __('app.outstanding_balance') }}</th>
                        <th class="text-center">{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    @php($remaining = $order->remainingBalance())
                    <tr>
                        <td class="fw-semibold">{{ $order->order_number }}</td>
                        <td class="small">{{ $order->received_at?->format(date_fmt()) }}</td>
                        <td class="small">{{ $order->completed_at?->format(date_fmt()) ?? "-" }}</td>
                        <td class="small {{ $remaining > 0 && $order->isOverdue() ? "text-danger fw-semibold" : "" }}">{{ $order->due_date?->format(date_fmt()) ?? "-" }}</td>
                        <td class="small">@if($order->invoice)<a href="{{ route("invoices.show", $order->invoice) }}">{{ $order->invoice->label() }}</a>@else - @endif</td>
                        <td class="text-end">{{ num($order->total_price) }}</td>
                        <td class="text-end text-success">{{ num($order->paidAmount()) }}</td>
                        <td class="text-end fw-bold {{ $remaining > 0 ? "text-danger" : "" }}">{{ $remaining > 0 ? num($remaining) : "0" }}</td>
                        <td class="text-center">
                            @if($remaining <= 0)
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> {{ __('app.paid') }}</span>
                            @elseif($order->isOverdue())
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
                        <td colspan="10" class="text-center py-4 text-muted">{{ __('app.no_transactions') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
