@extends("layouts.admin")
@section("title", $type === "pickup" ? __('app.new_pickup') : __('app.schedule_delivery'))
@section("page_title", $type === "pickup" ? __('app.new_pickup') : __('app.schedule_delivery'))

@php
    $defaults = $customer ? [
        "contact_name" => $customer->name,
        "contact_phone" => $customer->phone,
        "address" => $customer->address,
        "address_reference" => $customer->address_reference,
        "delivery_zone_id" => $customer->delivery_zone_id,
    ] : [];
    if ($order) {
        $defaults["scheduled_date"] = ($order->delivery_date && $order->delivery_date->gte(today()) ? $order->delivery_date : today())->toDateString();
    }
@endphp

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body">
                <form action="{{ route("logistics.store") }}" method="POST">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">

                    @if($type === "delivery")
                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                        <div class="alert alert-light border d-flex justify-content-between flex-wrap gap-2">
                            <span><i class="bi bi-receipt"></i> <strong>{{ $order->order_number }}</strong> · {{ $order->customer?->name }}</span>
                            <span>{{ __('app.total') }}: {{ money($order->total_price) }} · {{ __('app.remaining') }}: {{ money($order->remainingBalance()) }}</span>
                        </div>
                        @error("order_id") <div class="alert alert-danger">{{ $message }}</div> @enderror
                        <p class="small text-muted">{{ __('app.delivery_fee_added_hint') }}</p>
                    @else
                        <div class="mb-3">
                            <label class="form-label" for="customer_id">{{ __('app.customer') }} <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" required class="form-select @error("customer_id") is-invalid @enderror">
                                <option value="">{{ __('app.select_customer') }}</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" data-name="{{ $c->name }}" data-phone="{{ $c->phone }}" data-address="{{ $c->address }}" data-reference="{{ $c->address_reference }}" data-zone="{{ $c->delivery_zone_id }}"
                                        @selected((string) old("customer_id", $customer?->id) === (string) $c->id)>{{ $c->name }} · {{ $c->phone }}</option>
                                @endforeach
                            </select>
                            @error("customer_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <a href="{{ route("customers.create") }}" target="_blank" class="small"><i class="bi bi-plus-circle"></i> {{ __('app.new_customer') }}</a>
                        </div>
                    @endif

                    @include("logistics._fields", ["defaults" => $defaults])

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-check"></i> {{ __('app.schedule') }}</button>
                        <a href="{{ $order ? route("orders.show", $order) : route("logistics.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@if($type === "pickup")
@push("scripts")
    @vite("resources/js/pages/courier-job-form.js")
@endpush
@endif
