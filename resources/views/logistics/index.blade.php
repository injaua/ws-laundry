@extends("layouts.admin")
@section("title", __('app.logistics'))
@section("page_title", __('app.logistics'))
@section("page_subtitle", __('app.logistics_subtitle'))

@section("content")
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" data-auto-submit>
        <select name="courier" class="form-select" data-auto-submit>
            <option value="">{{ __('app.all_couriers') }}</option>
            @foreach($couriers as $c)
                <option value="{{ $c->id }}" @selected($courierId === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </form>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route("logistics.create", ["type" => "pickup"]) }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> {{ __('app.new_pickup') }}</a>
        <a href="{{ route("logistics.route-sheet", ["date" => $date->toDateString(), "courier" => $courierId]) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('app.route_sheet') }}</a>
        <a href="{{ route("logistics.cash") }}" class="btn btn-outline-{{ $cashPending > 0 ? "warning" : "secondary" }}">
            <i class="bi bi-cash-coin"></i> {{ __('app.courier_cash') }}@if($cashPending > 0) · {{ money($cashPending) }} @endif
        </a>
        @if(auth()->user()->hasRole(["admin", "owner"]))
        <a href="{{ route("delivery-zones.index") }}" class="btn btn-outline-secondary"><i class="bi bi-geo"></i> {{ __('app.delivery_zones') }}</a>
        @endif
    </div>
</div>

@if($requested->isNotEmpty())
<div class="card mb-3 border-warning">
    <div class="card-header bg-warning-subtle"><i class="bi bi-globe"></i> {{ __('app.pickup_requests_pending') }} ({{ $requested->count() }})</div>
    <div class="list-group list-group-flush">
        @foreach($requested as $job)
        <a href="{{ route("logistics.show", $job) }}" class="list-group-item list-group-item-action d-flex justify-content-between flex-wrap gap-2">
            <span><strong>{{ $job->contact_name }}</strong> · {{ $job->contact_phone }} · {{ $job->address }}@if($job->zone) ({{ $job->zone->name }})@endif</span>
            <span class="small text-muted">{{ $job->scheduled_date->format(date_fmt()) }} · {{ __('app.window_' . $job->time_window) }} · {{ $job->created_at->diffForHumans() }}</span>
        </a>
        @endforeach
    </div>
</div>
@endif

@if($awaitingOrder->isNotEmpty())
<div class="card mb-3 border-info">
    <div class="card-header bg-info-subtle"><i class="bi bi-bag-check"></i> {{ __('app.pickups_awaiting_order') }} ({{ $awaitingOrder->count() }})</div>
    <div class="list-group list-group-flush">
        @foreach($awaitingOrder as $job)
        <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>
                <strong>{{ $job->customer?->name ?? $job->contact_name }}</strong> · {{ $job->completed_at?->format("d/m H:i") }} · {{ $job->courier?->name }}
                @if($job->outcome_notes)<span class="text-muted small">— {{ $job->outcome_notes }}</span>@endif
            </span>
            <a href="{{ route("orders.create", ["pickup" => $job->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('app.create_order_from_pickup') }}</a>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($overdue->isNotEmpty())
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i> {{ __('app.trips_left_over', ["count" => $overdue->count()]) }}
    @foreach($overdue as $job)
        <a href="{{ route("logistics.show", $job) }}" class="badge bg-danger text-decoration-none">{{ $job->scheduled_date->format("d/m") }} · {{ $job->customer?->name ?? $job->contact_name }}</a>
    @endforeach
</div>
@endif

<div class="card">
    <div class="card-header"><i class="bi bi-calendar-day"></i> {{ __('app.trips_on', ["date" => $date->format(date_fmt())]) }}</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.type') }}</th>
                        <th>{{ __('app.customer') }}</th>
                        <th>{{ __('app.address') }}</th>
                        <th>{{ __('app.time_window') }}</th>
                        <th>{{ __('app.courier') }}</th>
                        <th class="text-end">{{ __('app.delivery_fee') }}</th>
                        <th class="text-center">{{ __('app.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                    <tr data-href="{{ route("logistics.show", $job) }}">
                        <td><span class="badge bg-{{ $job->isPickup() ? "info" : "success" }}">{{ __('app.job_type_' . $job->type) }}</span></td>
                        <td>
                            {{ $job->customer?->name ?? $job->contact_name }}
                            @if($job->order)<div class="small text-muted">{{ $job->order->order_number }}</div>@endif
                        </td>
                        <td class="small">{{ $job->address }}@if($job->zone)<div class="text-muted">{{ $job->zone->name }}</div>@endif</td>
                        <td class="small">{{ __('app.window_' . $job->time_window) }}</td>
                        <td class="small">{{ $job->courier?->name ?? "—" }}</td>
                        <td class="text-end">{{ money($job->fee) }}</td>
                        <td class="text-center"><span class="badge bg-{{ $job->statusColor() }}">{{ __('app.job_status_' . $job->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('app.no_trips') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<p class="small text-muted mt-2">{{ __('app.delivery_from_order_hint') }}</p>
@endsection
