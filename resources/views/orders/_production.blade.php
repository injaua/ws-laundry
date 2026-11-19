{{-- Production: intake inspection, photos, machine loads, stage history, quality and rework --}}
@php($inProduction = in_array($order->status, \App\Models\Order::PRODUCTION_STAGES, true) && !$order->abandoned_at)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-kanban me-1"></i> {{ __('app.production') }}</span>
        <span>
            @if($order->isLate())<span class="badge bg-danger">{{ __('app.late') }}</span>@elseif($order->isDueToday())<span class="badge bg-warning text-dark">{{ __('app.due_today') }}</span>@endif
            @if($order->qc_passed_at)<span class="badge bg-success" title="{{ $order->qcUser?->name }} · {{ $order->qc_passed_at->format('d/m H:i') }}"><i class="bi bi-patch-check"></i> {{ __('app.qc_ok') }}</span>@endif
            @if($order->rework_count)<span class="badge bg-warning text-dark"><i class="bi bi-arrow-counterclockwise"></i> {{ $order->rework_count }}</span>@endif
            @if($inProduction)<a href="{{ route('production.board') }}" class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-kanban"></i></a>@endif
        </span>
    </div>
    <div class="card-body">
        @if($order->reworkOf)
            <div class="alert alert-warning py-2 small">{{ __('app.rework_of_order') }} <a href="{{ route('orders.show', $order->reworkOf) }}">{{ $order->reworkOf->order_number }}</a></div>
        @endif
        @if($order->original_delivery_date)
            <div class="small mb-2"><i class="bi bi-calendar-x text-danger"></i> {{ __('app.rescheduled_from', ['from' => $order->original_delivery_date->format(date_fmt()), 'to' => $order->delivery_date?->format(date_fmt())]) }}
                @if($order->delay_reason) · <span class="text-muted">{{ $order->delay_reason }}</span>@endif</div>
        @endif

        {{-- Intake inspection --}}
        <form method="POST" action="{{ route('production.intake', $order) }}" class="mb-3">
            @csrf @method('PUT')
            <div class="fw-semibold small mb-1">{{ __('app.intake_inspection') }}</div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach(\App\Models\Order::CARE_FLAGS as $flag)
                    <div class="form-check form-check-inline me-0">
                        <input class="form-check-input" type="checkbox" name="care_flags[]" value="{{ $flag }}" id="o_care_{{ $flag }}" @checked(in_array($flag, $order->care_flags ?? []))>
                        <label class="form-check-label small" for="o_care_{{ $flag }}">{{ __('app.care_' . $flag) }}</label>
                    </div>
                @endforeach
            </div>
            <div class="input-group input-group-sm">
                <input type="text" name="intake_notes" maxlength="1000" class="form-control" value="{{ $order->intake_notes }}" placeholder="{{ __('app.intake_notes_placeholder') }}">
                <button class="btn btn-outline-primary">{{ __('app.save') }}</button>
            </div>
        </form>

        {{-- Photos --}}
        <div class="fw-semibold small mb-1">{{ __('app.intake_photos') }}</div>
        <div class="d-flex flex-wrap gap-2 mb-2">
            @foreach($order->photos as $photo)
                <div class="position-relative">
                    <a href="{{ $photo->url() }}" target="_blank"><img src="{{ $photo->url() }}" alt="{{ $photo->caption }}" class="rounded border thumb-84"></a>
                    @if(auth()->user()->hasRole(['admin', 'owner']))
                        <form method="POST" action="{{ route('production.photos.destroy', [$order, $photo]) }}" class="position-absolute top-0 end-0" data-confirm="{{ __('app.confirm_delete') }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger py-0 px-1"><i class="bi bi-x"></i></button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('production.photos.store', $order) }}" enctype="multipart/form-data" class="input-group input-group-sm mb-3">
            @csrf
            <input type="file" name="photos[]" accept="image/*" capture="environment" multiple required class="form-control @error('photos.*') is-invalid @enderror">
            <input type="text" name="caption" maxlength="150" class="form-control" placeholder="{{ __('app.caption') }}">
            <button class="btn btn-outline-primary"><i class="bi bi-camera"></i></button>
        </form>

        @if($order->machineLoads->isNotEmpty())
            <div class="fw-semibold small mb-1">{{ __('app.machine_loads') }}</div>
            <ul class="list-unstyled small mb-3">
                @foreach($order->machineLoads as $load)
                    <li><a href="{{ route('production.loads.show', $load) }}">{{ $load->code }}</a> · {{ __('app.status_' . $load->stage) }} · {{ $load->equipment?->name }}
                        · {{ rtrim(rtrim($load->pivot->weight_kg, '0'), '.') }} kg @if($load->program)· {{ $load->program }}@endif
                        @if($load->employee) · {{ $load->employee->name }}@endif
                        <span class="badge bg-{{ $load->statusColor() }}">{{ __('app.load_status_' . $load->status) }}</span></li>
                @endforeach
            </ul>
        @endif

        @if($order->reworks->isNotEmpty())
            <div class="fw-semibold small mb-1">{{ __('app.rework') }}</div>
            <ul class="list-unstyled small mb-3">
                @foreach($order->reworks as $rw)
                    <li class="text-warning-emphasis"><i class="bi bi-arrow-counterclockwise"></i> {{ $rw->created_at->format('d/m H:i') }} · {{ __('app.rework_source_' . $rw->source) }} · {{ __('app.status_' . $rw->from_status) }} · {{ $rw->reason }} <span class="text-muted">({{ $rw->user?->name }})</span></li>
                @endforeach
            </ul>
        @endif

        <div class="fw-semibold small mb-1">{{ __('app.stage_history') }}</div>
        @php($logs = $order->stageLogs->values())
        <ul class="list-unstyled small mb-0">
            @foreach($logs as $i => $log)
                @php($next = $logs[$i + 1] ?? null)
                <li class="d-flex justify-content-between border-bottom py-1">
                    <span>
                        <strong>{{ __('app.status_' . $log->to_status) }}</strong>
                        @if($log->note === 'rework')<span class="badge bg-warning text-dark">{{ __('app.rework') }}</span>@endif
                        <span class="text-muted">· {{ $log->employee?->name ?? $log->user?->name ?? '—' }}</span>
                    </span>
                    <span class="text-muted">{{ $log->created_at->format('d/m H:i') }}@if($next) · {{ $log->created_at->diffForHumans($next->created_at, true) }}@endif</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
