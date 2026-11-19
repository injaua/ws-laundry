@extends("layouts.admin")
@section("title", $supplier->name)
@section("page_title", __('app.suppliers') . ": " . $supplier->name)

@section("content")
<div class="page-title">
    <span>
        @if($supplier->is_active)
            <span class="badge bg-success-subtle text-success">{{ __('app.active') }}</span>
        @else
            <span class="badge bg-secondary">{{ __('app.inactive') }}</span>
        @endif
    </span>
    @if(auth()->user()->isManager())
    <a href="{{ route("resources.suppliers.edit", $supplier) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('app.edit') }}</a>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="text-muted w-40pct">{{ __('app.contact_person') }}</td><td>{{ $supplier->contact_person ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.phone') }}</td><td>{{ $supplier->phone ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.email') }}</td><td>{{ $supplier->email ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.nit') }}</td><td>{{ $supplier->nit ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.address') }}</td><td>{{ $supplier->address ?: "—" }}{{ $supplier->city ? ", " . $supplier->city : "" }}</td></tr>
                    </tbody>
                </table>
                @if($supplier->notes)
                    <hr>
                    <p class="small mb-0"><strong>{{ __('app.notes') }}:</strong> {{ $supplier->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">{{ __('app.purchase_orders') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.po_number') }}</th>
                                <th>{{ __('app.order_date') }}</th>
                                <th class="text-end">{{ __('app.total') }}</th>
                                <th>{{ __('app.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchaseOrders as $order)
                            <tr>
                                <td><a href="{{ route("resources.purchases.show", $order) }}" class="text-decoration-none fw-medium">{{ $order->po_number }}</a></td>
                                <td class="small">{{ $order->order_date->format(date_fmt()) }}</td>
                                <td class="text-end small">{{ money($order->total) }}</td>
                                <td><span class="badge badge-status bg-{{ $order->status_color }}">{{ $order->status_label }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-3 text-muted small">{{ __('app.no_purchase_orders') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
