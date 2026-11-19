@extends("layouts.admin")
@section("title", __('app.customer_detail'))
@section("page_title", __('app.customer_detail'))

@section("content")
<div class="row">
    <div class="col-md-4">
        {{-- Customer info card --}}
        <div class="card mb-3">
            <div class="card-header">
                <i class="bi bi-person me-1"></i> {{ __('app.customer_info') }}
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto avatar-72"
                       >
                        {{ strtoupper(substr($customer->name, 0, 1)) }}
                    </div>
                    <h5 class="mt-2 mb-0 fw-semibold">{{ $customer->name }}</h5>
                    @if($customer->isBusiness())
                        <span class="badge bg-dark mt-1"><i class="bi bi-building"></i> {{ __('app.customer_type_business') }}</span>
                    @endif
                </div>
                <hr>
                @if($customer->isBusiness())
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-building me-1"></i> {{ __('app.legal_name') }}</small>
                    <span>{{ $customer->legal_name ?? "-" }}</span>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-upc me-1"></i> {{ __('app.nit') }}</small>
                    <span class="font-monospace">{{ $customer->nuit ?? "-" }}</span>
                </div>
                @if($customer->contact_person)
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-person-badge me-1"></i> {{ __('app.contact_person') }}</small>
                    <span>{{ $customer->contact_person }}</span>
                </div>
                @endif
                @endif
                @if($customer->email)
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-envelope me-1"></i> {{ __('app.billing_email') }}</small>
                    <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>
                </div>
                @endif
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i> {{ __('app.phone') }}</small>
                    <span>{{ $customer->phone }}</span>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-geo-alt me-1"></i> {{ __('app.address') }}</small>
                    <span>{{ $customer->address ?? "-" }}</span>
                    @if($customer->deliveryZone || $customer->address_reference)
                        <div class="small text-muted">{{ $customer->deliveryZone?->name }}@if($customer->deliveryZone && $customer->address_reference) · @endif{{ $customer->address_reference }}</div>
                    @endif
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-receipt me-1"></i> {{ __('app.total_orders') }}</small>
                    <span class="badge bg-secondary fs-6">{{ $customer->orders_count ?? 0 }}</span>
                </div>
                <div>
                    <small class="text-muted d-block"><i class="bi bi-calendar me-1"></i> {{ __('app.joined_since') }}</small>
                    <span>{{ $customer->created_at->format(date_fmt()) }}</span>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                @if(auth()->user()->isManager())
                <a href="{{ route("customers.edit", $customer) }}" class="btn btn-outline-primary btn-sm flex-fill">
                    <i class="bi bi-pencil"></i> {{ __('app.edit') }}
                </a>
                @endif
                @unless(auth()->user()->isAccountant())
                <a href="{{ route("logistics.create", ["type" => "pickup", "customer_id" => $customer->id]) }}" class="btn btn-outline-success btn-sm flex-fill">
                    <i class="bi bi-truck"></i> {{ __('app.schedule_pickup') }}
                </a>
                @endunless
                <a href="{{ route("customers.index") }}" class="btn btn-outline-secondary btn-sm flex-fill">
                    <i class="bi bi-arrow-left"></i> {{ __('app.back') }}
                </a>
            </div>
        </div>

    @if($receivables)
    <div class="card mb-3">
        <div class="card-header">
            <i class="bi bi-cash-coin me-1"></i> {{ __('app.receivables') }}
            <span class="badge bg-dark float-end">{{ __('app.credit_customer') }}</span>
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <span class="small text-muted">{{ __('app.total_outstanding') }}</span>
                <span class="fw-bold">{{ money($receivables["outstanding"]) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="small text-muted">{{ __('app.overdue_amount') }}</span>
                <span class="{{ $receivables["overdue_count"] > 0 ? "text-danger fw-bold" : "text-success" }}">
                    {{ money($receivables["overdue_amount"]) }}
                    @if($receivables["overdue_count"] > 0)· {{ $receivables["overdue_count"] }} @endif
                </span>
            </div>
            <div class="d-flex justify-content-between mb-3">
                <span class="small text-muted">{{ __('app.credit_limit') }}</span>
                <span>{{ $customer->credit_limit > 0 ? money($customer->credit_limit) : __('app.unlimited') }}</span>
            </div>
            <div class="d-flex justify-content-between mb-3">
                <span class="small text-muted">{{ __('app.billing_cycle') }}</span>
                <span>{{ __('app.billing_cycle_' . $customer->billing_cycle) }}</span>
            </div>
            @if(!auth()->user()->isCashier())
            <div class="d-grid gap-2">
                <a href="{{ route("receivables.customer", $customer) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-cash-coin"></i> {{ __('app.customer_account') }}
                </a>
                <a href="{{ route("customers.statement", $customer) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                    <i class="bi bi-printer"></i> {{ __('app.statement_of_account') }}
                </a>
                <a href="{{ route("invoices.create", ["customer_id" => $customer->id]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-file-earmark-plus"></i> {{ __('app.new_invoice') }}
                </a>
            </div>
            @endif
        </div>
    </div>

    @if($invoices->isNotEmpty() && !auth()->user()->isCashier())
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-files me-1"></i> {{ __('app.invoices') }}</div>
        <ul class="list-group list-group-flush">
            @foreach($invoices as $invoice)
            <a href="{{ route("invoices.show", $invoice) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span>
                    <span class="fw-semibold">{{ $invoice->label() }}</span>
                    <span class="small text-muted d-block">{{ $invoice->period_start->format(date_fmt()) }} – {{ $invoice->period_end->format(date_fmt()) }}</span>
                </span>
                <span class="text-end">
                    <span class="d-block small">{{ money((float) $invoice->total) }}</span>
                    <span class="badge bg-{{ $invoice->statusColor() }}">{{ __('app.invoice_status_' . $invoice->displayStatus()) }}</span>
                </span>
            </a>
            @endforeach
        </ul>
    </div>
    @endif
    @endif

    @include("customers._negotiated-prices")

    @include("customers._personal-data")
    </div>

    <div class="col-md-8">
        {{-- Order list --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-receipt me-1"></i> {{ __('app.transactions') }}</span>
                <span class="text-muted small">{{ __('app.total_transactions', ['count' => $orders->total()]) }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.order_number') }}</th>
                                <th>{{ __('app.entry_date') }}</th>
                                <th>{{ __('app.status') }}</th>
                                <th>{{ __('app.total') }}</th>
                                <th class="text-center">{{ __('app.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number }}</td>
                                <td class="small">{{ $order->received_at->format(date_fmt()) }}</td>
                                <td>
                                    <span class="badge bg-{{ $order->status_color }} badge-status">
                                        <i class="bi bi-{{ $order->status == "completed" ? "check-circle" : "hourglass-split" }}"></i>
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                                <td class="fw-semibold">{{ money($order->total_price) }}</td>
                                <td class="text-center">
                                    <a href="{{ route("orders.show", $order) }}" class="btn btn-sm btn-outline-info" title="{{ __('app.view') }}">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-receipt fs-1 d-block mb-2"></i>
                                    {{ __('app.no_transactions') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $orders->links() }}
        </div>

        @if($messageLogs->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-whatsapp me-1"></i> {{ __('app.comms_history') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <tbody>
                            @foreach($messageLogs as $log)
                            <tr>
                                <td class="text-nowrap small text-muted">{{ $log->created_at->format(datetime_fmt()) }}</td>
                                <td class="small">{{ $log->sender?->name }}</td>
                                <td class="small text-truncate mw-320px" title="{{ $log->body }}">{{ $log->body }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
