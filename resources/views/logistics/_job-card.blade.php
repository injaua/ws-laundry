{{-- A trip as the courier sees it on the phone. Expects: $job. --}}
<div class="card mb-3 border-start border-4 border-{{ $job->isPickup() ? "info" : "success" }}">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
            <div>
                <span class="badge bg-{{ $job->isPickup() ? "info" : "success" }}">
                    <i class="bi bi-{{ $job->isPickup() ? "box-arrow-in-down" : "box-arrow-up" }}"></i> {{ __('app.job_type_' . $job->type) }}
                </span>
                <span class="badge bg-light text-dark border">{{ __('app.window_' . $job->time_window) }}</span>
                @if($job->scheduled_date->lt(today()))<span class="badge bg-danger">{{ $job->scheduled_date->format("d/m") }}</span>@endif
            </div>
            <span class="badge bg-{{ $job->statusColor() }}">{{ __('app.job_status_' . $job->status) }}</span>
        </div>

        <div class="fw-semibold fs-6">{{ $job->customer?->name ?? $job->contact_name }}</div>
        <div class="small">
            <i class="bi bi-geo-alt text-danger"></i> {{ $job->address }}@if($job->zone) · <span class="text-muted">{{ $job->zone->name }}</span>@endif
            @if($job->address_reference)<div class="text-muted ms-3">{{ $job->address_reference }}</div>@endif
        </div>
        @if($job->notes)<div class="small fst-italic text-muted mt-1"><i class="bi bi-sticky"></i> {{ $job->notes }}</div>@endif

        @if($job->isDelivery() && $job->order)
            <div class="small mt-1">
                <i class="bi bi-receipt"></i> {{ $job->order->order_number }}
                @if(($collect = $job->amountToCollect()) > 0)
                    · <strong class="text-danger">{{ __('app.to_collect') }}: {{ money($collect) }}</strong>
                @elseif($job->order->is_credit)
                    · <span class="text-muted">{{ __('app.credit_sale') }}</span>
                @else
                    · <span class="text-success">{{ __('app.paid') }}</span>
                @endif
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2 my-2">
            <a href="tel:{{ $job->contact_phone }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-telephone"></i> {{ __('app.call') }}</a>
            @if($wa = $job->whatsappUrl())
                <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i></a>
            @endif
            <a href="{{ $job->mapUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-map"></i> {{ __('app.open_map') }}</a>
        </div>

        @include("logistics._actions", ["job" => $job])
    </div>
</div>
