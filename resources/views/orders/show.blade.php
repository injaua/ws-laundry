@extends("layouts.admin")
@section("title", __('app.order_detail'))
@section("page_title", __('app.order_detail') . " #" . $order->order_number)

@section("content")
<div class="row">
    <div class="col-md-8">
        {{-- Order information --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-1"></i> {{ __('app.order_info') }}</span>
                <span class="badge bg-{{ $order->status_color }} fs-6">{{ $order->status_label }}</span>
                @if($order->materials_consumed_at)
                    <span class="badge bg-light text-dark ms-1" title="{{ $order->materials_consumed_at->format(datetime_fmt()) }}"><i class="bi bi-droplet-half"></i> {{ __('app.materials_consumed') }}</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.order_number') }}</small>
                        <span class="fw-semibold">{{ $order->order_number }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.customer') }}</small>
                        <span class="fw-semibold">{{ $order->customer->name }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.phone') }}</small>
                        <span>{{ $order->customer->phone }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.entry_date') }}</small>
                        <span>{{ $order->received_at->format(date_fmt()) }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.delivery_date') }}</small>
                        <span>{{ $order->delivery_date?->format(date_fmt()) ?? "-" }}</span>
                        @if($order->is_express)<span class="badge bg-warning text-dark ms-1"><i class="bi bi-lightning-charge"></i> {{ __('app.express') }}</span>@endif
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.completion_date') }}</small>
                        <span>{{ ($order->completed_at ?? $order->delivery_date)?->format(date_fmt()) ?? "-" }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.total') }}</small>
                        <span class="fw-bold fs-5 text-primary">{{ money($order->total_price) }}</span>
                    </div>
                </div>
                @if($order->notes)
                    <div class="mt-2 p-3 bg-light rounded">
                        <small class="text-muted d-block">{{ __('app.notes') }}:</small>
                        {{ $order->notes }}
                    </div>
                @endif
            </div>
        </div>

        @include("orders._items")

        @can("update", $order)
            @include("orders._production")
            @include("orders._pieces")
        @endcan
        @include("orders._after-sales")
    </div>

    <div class="col-md-4">
        @can("update", $order)
            @include("orders._logistics")
            @include("mobile-payments._charge", ["order" => $order])
            @include("orders._status")
        @endcan

        @include("orders._payments")
    </div>
</div>

<div class="mt-3">
    <a href="{{ route("orders.index") }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.back') }}</a>
    <a href="{{ route("orders.invoice", $order) }}" class="btn btn-outline-primary" target="_blank"><i class="bi bi-printer"></i> {{ __('app.print_invoice') }}</a>
    @can("update", $order)
    <a href="{{ route("orders.label", $order) }}" class="btn btn-outline-info" target="_blank"><i class="bi bi-tag"></i> {{ __('app.print_label') }}</a>
    @endcan
    @if(auth()->user()->isManager())
    <form action="{{ route("orders.destroy", $order) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_order_confirm') }}">
        @csrf @method("DELETE")
        <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('app.delete') }}</button>
    </form>
    @endif
</div>
@endsection
