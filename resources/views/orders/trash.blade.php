@extends("layouts.admin")
@section("title", __('app.trash_orders'))
@section("page_title", __('app.trash_orders'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_deleted_orders', ['count' => $orders->total()]) }}</span>
    <div>
        <a href="{{ route("orders.index") }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> {{ __('app.back') }}
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.order_number') }}</th>
                        <th>{{ __('app.customer') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.total') }}</th>
                        <th>{{ __('app.deleted_at') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td>{{ $loop->iteration + ($orders->currentPage() - 1) * $orders->perPage() }}</td>
                        <td class="fw-medium">{{ $order->order_number }}</td>
                        <td>{{ $order->customer->name ?? "-" }}</td>
                        <td><span class="badge bg-secondary">{{ $order->status_label }}</span></td>
                        <td>{{ money($order->total_price) }}</td>
                        <td class="small text-muted">{{ $order->deleted_at->format(datetime_fmt()) }}</td>
                        <td class="text-center">
                            <form action="{{ route("orders.restore", $order->id) }}" method="POST" class="d-inline" data-confirm="{{ __('app.restore_order_confirm') }}">
                                @csrf @method("PATCH")
                                <button class="btn btn-sm btn-success" title="{{ __('app.restore') }}"><i class="bi bi-arrow-counterclockwise"></i> {{ __('app.restore') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-trash fs-1 d-block mb-2"></i>
                            {{ __('app.no_deleted_orders') }}
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
@endsection
