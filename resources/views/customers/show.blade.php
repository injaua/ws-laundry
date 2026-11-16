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
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto"
                        style="width: 72px; height: 72px; font-size: 1.75rem; font-weight: 700;">
                        {{ strtoupper(substr($customer->name, 0, 1)) }}
                    </div>
                    <h5 class="mt-2 mb-0 fw-semibold">{{ $customer->name }}</h5>
                </div>
                <hr>
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i> {{ __('app.phone') }}</small>
                    <span>{{ $customer->phone }}</span>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-geo-alt me-1"></i> {{ __('app.address') }}</small>
                    <span>{{ $customer->address ?? "-" }}</span>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-receipt me-1"></i> {{ __('app.total_orders') }}</small>
                    <span class="badge bg-secondary fs-6">{{ $customer->orders_count ?? 0 }}</span>
                </div>
                <div>
                    <small class="text-muted d-block"><i class="bi bi-calendar me-1"></i> {{ __('app.joined_since') }}</small>
                    <span>{{ $customer->created_at->format("d/m/Y") }}</span>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                @if(!auth()->user()->isCashier())
                <a href="{{ route("customers.edit", $customer) }}" class="btn btn-outline-primary btn-sm flex-fill">
                    <i class="bi bi-pencil"></i> {{ __('app.edit') }}
                </a>
                @endif
                <a href="{{ route("customers.index") }}" class="btn btn-outline-secondary btn-sm flex-fill">
                    <i class="bi bi-arrow-left"></i> {{ __('app.back') }}
                </a>
            </div>
        </div>
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
                                <td class="small">{{ $order->received_at->format("d/m/Y") }}</td>
                                <td>
                                    <span class="badge bg-{{ $order->status_color }} badge-status">
                                        <i class="bi bi-{{ $order->status == "completed" ? "check-circle" : "hourglass-split" }}"></i>
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                                <td class="fw-semibold">{{ number_format($order->total_price, 0, ".", ",") }} Mts</td>
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
    </div>
</div>
@endsection
