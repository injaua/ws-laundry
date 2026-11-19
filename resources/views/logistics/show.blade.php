@extends("layouts.admin")
@section("title", __('app.job_type_' . $job->type) . " #" . $job->id)
@section("page_title", __('app.job_type_' . $job->type) . " #" . $job->id)

@section("content")
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="badge bg-{{ $job->isPickup() ? "info" : "success" }} fs-6">{{ __('app.job_type_' . $job->type) }}</span>
                    <span class="badge bg-{{ $job->statusColor() }} fs-6">{{ __('app.job_status_' . $job->status) }}</span>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-5">{{ __('app.customer') }}</dt>
                    <dd class="col-7">
                        @if($job->customer)<a href="{{ route("customers.show", $job->customer) }}">{{ $job->customer->name }}</a>@else{{ $job->contact_name }}@endif
                    </dd>
                    <dt class="col-5">{{ __('app.phone') }}</dt>
                    <dd class="col-7"><a href="tel:{{ $job->contact_phone }}">{{ $job->contact_phone }}</a>@if($wa = $job->whatsappUrl()) · <a href="{{ $wa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp text-success"></i></a>@endif</dd>
                    <dt class="col-5">{{ __('app.address') }}</dt>
                    <dd class="col-7">{{ $job->address }} · <a href="{{ $job->mapUrl() }}" target="_blank" rel="noopener"><i class="bi bi-map"></i></a>@if($job->address_reference)<div class="text-muted">{{ $job->address_reference }}</div>@endif</dd>
                    <dt class="col-5">{{ __('app.delivery_zone') }}</dt>
                    <dd class="col-7">{{ $job->zone?->name ?? "—" }}</dd>
                    <dt class="col-5">{{ __('app.date') }}</dt>
                    <dd class="col-7">{{ $job->scheduled_date->format(date_fmt()) }} · {{ __('app.window_' . $job->time_window) }}</dd>
                    <dt class="col-5">{{ __('app.courier') }}</dt>
                    <dd class="col-7">{{ $job->courier?->name ?? __('app.unassigned') }}</dd>
                    <dt class="col-5">{{ __('app.delivery_fee') }}</dt>
                    <dd class="col-7">{{ money($job->fee) }}</dd>
                    @if($job->order)
                    <dt class="col-5">{{ __('app.order_number') }}</dt>
                    <dd class="col-7"><a href="{{ route("orders.show", $job->order) }}">{{ $job->order->order_number }}</a></dd>
                    @endif
                    <dt class="col-5">{{ __('app.source') }}</dt>
                    <dd class="col-7">{{ __('app.job_source_' . $job->source) }}@if($job->creator) · {{ $job->creator->name }}@endif</dd>
                    @if($job->notes)
                    <dt class="col-5">{{ __('app.notes') }}</dt>
                    <dd class="col-7">{{ $job->notes }}</dd>
                    @endif
                    @if($job->outcome_notes)
                    <dt class="col-5">{{ __('app.outcome') }}</dt>
                    <dd class="col-7">{{ $job->outcome_notes }}</dd>
                    @endif
                    @if($job->failure_reason)
                    <dt class="col-5">{{ __('app.failure_reason') }}</dt>
                    <dd class="col-7 text-danger">{{ $job->failure_reason }}</dd>
                    @endif
                    @if($job->completed_at)
                    <dt class="col-5">{{ __('app.completed_at') }}</dt>
                    <dd class="col-7">{{ $job->completed_at->format(datetime_fmt()) }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        @if($job->payments->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-cash-coin"></i> {{ __('app.collected_on_delivery') }}</div>
            <ul class="list-group list-group-flush small">
                @foreach($job->payments as $payment)
                <li class="list-group-item d-flex justify-content-between">
                    <span>{{ money($payment->amount) }} · {{ __('app.' . $payment->method) }} · {{ $payment->user?->name }}</span>
                    @if($payment->method === "cash")
                        <span class="badge bg-{{ $payment->handed_over_at ? "success" : "warning text-dark" }}">{{ $payment->handed_over_at ? __('app.handed_over') : __('app.with_courier') }}</span>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @if($job->awaitsOrder())
            <a href="{{ route("orders.create", ["pickup" => $job->id]) }}" class="btn btn-primary w-100 mb-3"><i class="bi bi-plus-lg"></i> {{ __('app.create_order_from_pickup') }}</a>
        @endif

        @if($job->isActionable())
        <div class="card mb-3">
            <div class="card-header">{{ __('app.record_on_behalf') }}</div>
            <div class="card-body">@include("logistics._actions", ["job" => $job])</div>
        </div>
        @endif

        @if($job->isOpen())
        <form action="{{ route("logistics.cancel", $job) }}" method="POST" data-confirm="{{ __('app.confirm_cancel_trip') }}">
            @csrf
            @error("status") <div class="alert alert-danger small">{{ $message }}</div> @enderror
            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-x-lg"></i> {{ __('app.cancel_trip') }}</button>
        </form>
        @endif
    </div>

    <div class="col-lg-7">
        @if($job->status === "requested")
        <div class="card mb-3 border-warning">
            <div class="card-header bg-warning-subtle"><i class="bi bi-globe"></i> {{ __('app.confirm_pickup_request') }}</div>
            <div class="card-body">
                <form action="{{ route("logistics.confirm", $job) }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label" for="confirm_customer">{{ __('app.customer') }}</label>
                        <select name="customer_id" id="confirm_customer" class="form-select">
                            <option value="">{{ $match ? __('app.use_matched_customer', ["name" => $match->name]) : __('app.register_new_customer') }}</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} · {{ $c->phone }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('app.confirm_customer_hint') }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="c_date">{{ __('app.date') }}</label>
                        <input type="date" name="scheduled_date" id="c_date" class="form-control" value="{{ $job->scheduled_date->toDateString() }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="c_window">{{ __('app.time_window') }}</label>
                        <select name="time_window" id="c_window" class="form-select">
                            @foreach(\App\Models\CourierJob::WINDOWS as $w)
                                <option value="{{ $w }}" @selected($job->time_window === $w)>{{ __('app.window_' . $w) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="c_courier">{{ __('app.courier') }}</label>
                        <select name="courier_id" id="c_courier" class="form-select">
                            <option value="">{{ __('app.unassigned') }}</option>
                            @foreach($couriers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-check2-circle"></i> {{ __('app.confirm_pickup_request') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        @if(in_array($job->status, ["requested", "scheduled", "failed"], true))
        <div class="card">
            <div class="card-header">{{ $job->status === "failed" ? __('app.reschedule_trip') : __('app.edit_trip') }}</div>
            <div class="card-body">
                <form action="{{ route("logistics.update", $job) }}" method="POST">
                    @csrf @method("PUT")
                    @include("logistics._fields", ["job" => $job])
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> {{ $job->status === "failed" ? __('app.reschedule_trip') : __('app.save') }}</button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
