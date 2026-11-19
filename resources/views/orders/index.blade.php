@extends("layouts.admin")
@section("title", __('app.transactions'))
@section("page_title", __('app.transactions'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_transactions', ['count' => $orders->total()]) }}</span>
    <div class="d-flex gap-2">
        @if(auth()->user()->isManager())
        <a href="{{ route("orders.trash") }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-trash"></i> {{ __('app.trash') }}
        </a>
        @endif
        @can("create", \App\Models\Order::class)
        <a href="{{ route("orders.create") }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> {{ __('app.new_transaction') }}
        </a>
        @endcan
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("orders.index") }}" method="GET" class="row g-2">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search_placeholder') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.all_statuses') }}</option>
                        <option value="received" {{ request("status") == "received" ? "selected" : "" }}>{{ __('app.status_received') }}</option>
                        <option value="washing" {{ request("status") == "washing" ? "selected" : "" }}>{{ __('app.status_washing') }}</option>
                        <option value="drying" {{ request("status") == "drying" ? "selected" : "" }}>{{ __('app.status_drying') }}</option>
                        <option value="ironing" {{ request("status") == "ironing" ? "selected" : "" }}>{{ __('app.status_ironing') }}</option>
                        <option value="folding" {{ request("status") == "folding" ? "selected" : "" }}>{{ __('app.status_folding') }}</option>
                        <option value="ready" {{ request("status") == "ready" ? "selected" : "" }}>{{ __('app.status_ready') }}</option>
                        <option value="delivering" {{ request("status") == "delivering" ? "selected" : "" }}>{{ __('app.status_delivering') }}</option>
                        <option value="completed" {{ request("status") == "completed" ? "selected" : "" }}>{{ __('app.status_completed') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> {{ __('app.filter') }}</button>
                    <a href="{{ route("orders.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.order_number') }}</th>
                        <th>{{ __('app.customer') }}</th>
                        <th>{{ __('app.entry_date') }}</th>
                        <th>{{ __('app.completion_date') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.total') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td class="fw-semibold">{{ $order->order_number }}@if($order->is_express) <i class="bi bi-lightning-charge-fill text-warning" title="{{ __('app.express') }}"></i>@endif</td>
                        <td>{{ $order->customer->name }}</td>
                        <td class="small">{{ $order->received_at->format(date_fmt()) }}</td>
                        <td class="small">{{ $order->completed_at?->format(date_fmt()) ?? "-" }}</td>
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
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" data-bs-boundary="window" aria-label="{{ __('app.actions') }}">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    @if(auth()->user()->isManager())
                                    <li><a href="{{ route("orders.edit", $order) }}" class="dropdown-item"><i class="bi bi-pencil me-2"></i>{{ __('app.edit') }}</a></li>
                                    @endif
                                    @can("update", $order)
                                    <li><a href="{{ route("orders.payment", $order) }}" class="dropdown-item"><i class="bi bi-cash me-2"></i>{{ __('app.payment') }}</a></li>
                                    @endcan
                                    <li><a href="{{ route("orders.invoice", $order) }}" class="dropdown-item" target="_blank"><i class="bi bi-printer me-2"></i>{{ __('app.invoice') }}</a></li>
                                    @if(auth()->user()->isManager())
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route("orders.destroy", $order) }}" method="POST" data-confirm="{{ __('app.delete_order_confirm') }}">
                                            @csrf @method("DELETE")
                                            <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>{{ __('app.delete') }}</button>
                                        </form>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-receipt fs-1 d-block mb-2"></i>
                            {{ __('app.no_transactions') }}@can("create", \App\Models\Order::class) <a href="{{ route("orders.create") }}">{{ __('app.create_new_transaction') }}</a>@endcan.
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
