@extends("layouts.admin")
@section("title", __('app.my_route'))
@section("page_title", __('app.my_route'))
@section("page_subtitle", today()->translatedFormat("l, d/m/Y"))

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        @if($cashHeld > 0)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-cash-coin fs-4"></i>
            <div>{!! __('app.courier_cash_held', ["amount" => "<strong>" . e(money($cashHeld)) . "</strong>"]) !!}</div>
        </div>
        @endif

        @forelse($jobs as $job)
            @include("logistics._job-card", ["job" => $job])
        @empty
            <div class="card"><div class="card-body text-center text-muted py-5">
                <i class="bi bi-emoji-smile fs-1 d-block mb-2"></i>{{ __('app.route_empty') }}
            </div></div>
        @endforelse

        @if($doneToday->isNotEmpty())
        <h6 class="text-muted mt-4">{{ __('app.done_today') }}</h6>
        <ul class="list-group mb-3">
            @foreach($doneToday as $job)
            <li class="list-group-item d-flex justify-content-between align-items-center small">
                <span>
                    <i class="bi bi-{{ $job->isPickup() ? "box-arrow-in-down" : "box-arrow-up" }}"></i>
                    {{ $job->customer?->name ?? $job->contact_name }}@if($job->order) · {{ $job->order->order_number }}@endif
                    @if($job->failure_reason)<div class="text-danger">{{ $job->failure_reason }}</div>@endif
                </span>
                <span class="badge bg-{{ $job->statusColor() }}">{{ __('app.job_status_' . $job->status) }}</span>
            </li>
            @endforeach
        </ul>
        @endif

        @if($upcoming->isNotEmpty())
        <h6 class="text-muted mt-4">{{ __('app.upcoming_trips') }}</h6>
        <ul class="list-group">
            @foreach($upcoming as $job)
            <li class="list-group-item small d-flex justify-content-between">
                <span>{{ $job->scheduled_date->format("d/m") }} · {{ __('app.job_type_' . $job->type) }} · {{ $job->customer?->name ?? $job->contact_name }}</span>
                <span class="text-muted">{{ $job->zone?->name }}</span>
            </li>
            @endforeach
        </ul>
        @endif
    </div>
</div>
@endsection
