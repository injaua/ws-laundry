{{-- Home pickup / delivery trips of this order. Expects: $order (with courierJobs.courier, courierJobs.zone). --}}
@php
    $jobs = $order->courierJobs;
    $canSchedule = $order->status !== "completed" && !$order->abandoned_at
        && !$jobs->contains(fn ($j) => $j->isDelivery() && $j->isOpen());
@endphp
@if($jobs->isNotEmpty() || $canSchedule)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-truck me-1"></i> {{ __('app.home_delivery') }}</span>
        @if($canSchedule)
            <a href="{{ route("logistics.create", ["type" => "delivery", "order_id" => $order->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar-plus"></i> {{ __('app.schedule_delivery') }}</a>
        @endif
    </div>
    @if($jobs->isNotEmpty())
    <ul class="list-group list-group-flush small">
        @foreach($jobs as $job)
        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
            <a href="{{ route("logistics.show", $job) }}" class="text-decoration-none">
                <i class="bi bi-{{ $job->isPickup() ? "box-arrow-in-down" : "box-arrow-up" }}"></i>
                {{ __('app.job_type_' . $job->type) }} · {{ $job->scheduled_date->format("d/m") }} · {{ $job->courier?->name ?? __('app.unassigned') }}
                @if((float) $job->fee > 0)<span class="text-muted">· {{ money($job->fee) }}</span>@endif
            </a>
            <span class="badge bg-{{ $job->statusColor() }}">{{ __('app.job_status_' . $job->status) }}</span>
        </li>
        @endforeach
    </ul>
    @else
    <div class="card-body small text-muted">{{ __('app.home_delivery_hint') }}</div>
    @endif
</div>
@endif
