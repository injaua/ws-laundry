@extends("layouts.admin")
@section("title", __('app.purchase_orders'))
@section("page_title", __('app.purchase_orders'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $orders->total()]) }}</span>
    @if(auth()->user()->isManager())
    <a href="{{ route("resources.purchases.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_purchase_order') }}
    </a>
    @endif
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("resources.purchases.index") }}" method="GET" class="row g-2">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="{{ __('app.po_number') }}" value="{{ request("search") }}">
                </div>
                <div class="col-md-3">
                    <select name="supplier_id" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.supplier') }}</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ request("supplier_id") == $supplier->id ? "selected" : "" }}>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.status') }}</option>
                        @foreach(\App\Models\PurchaseOrder::STATUSES as $st)
                            <option value="{{ $st }}" {{ request("status") == $st ? "selected" : "" }}>{{ __('app.po_status_' . $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> {{ __('app.search') }}</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.po_number') }}</th>
                        <th>{{ __('app.supplier') }}</th>
                        <th>{{ __('app.order_date') }}</th>
                        <th>{{ __('app.expected_date') }}</th>
                        <th class="text-end">{{ __('app.lines') }}</th>
                        <th class="text-end">{{ __('app.total') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td class="fw-medium">
                            <a href="{{ route("resources.purchases.show", $order) }}" class="text-decoration-none">{{ $order->po_number }}</a>
                        </td>
                        <td class="small">{{ $order->supplier->name ?? "—" }}</td>
                        <td class="small">{{ $order->order_date->format(date_fmt()) }}</td>
                        <td class="small">{{ $order->expected_date?->format(date_fmt()) ?? "—" }}</td>
                        <td class="text-end small">{{ $order->items->count() }}</td>
                        <td class="text-end">{{ money($order->total) }}</td>
                        <td><span class="badge badge-status bg-{{ $order->status_color }}">{{ $order->status_label }}</span></td>
                        <td class="text-center">
                            <a href="{{ route("resources.purchases.show", $order) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-bag-plus fs-1 d-block mb-2"></i>
                            {{ __('app.no_purchase_orders') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $orders->links() }}</div>
@endsection
