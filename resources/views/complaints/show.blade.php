@extends("layouts.admin")
@section("title", $complaint->number)
@section("page_title", __('app.complaint') . " " . $complaint->number)

@php($statusColors = ["open" => "danger", "in_progress" => "warning", "resolved" => "success", "rejected" => "secondary"])

@section("content")
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-chat-square-text me-1"></i> {{ __('app.complaint_category_' . $complaint->category) }}</span>
                <span class="badge bg-{{ $statusColors[$complaint->status] }}">{{ __('app.complaint_status_' . $complaint->status) }}</span>
            </div>
            <div class="card-body">
                <p class="pre-wrap">{{ $complaint->description }}</p>
                <dl class="row small mb-0">
                    <dt class="col-sm-4">{{ __('app.customer') }}</dt>
                    <dd class="col-sm-8"><a href="{{ route("customers.show", $complaint->customer_id) }}">{{ $complaint->customer?->name }}</a> {{ $complaint->customer?->phone }}</dd>
                    @if($complaint->order)
                    <dt class="col-sm-4">{{ __('app.transactions') }}</dt>
                    <dd class="col-sm-8"><a href="{{ route("orders.show", $complaint->order_id) }}">{{ $complaint->order->order_number }}</a> · {{ $complaint->order->status_label }}</dd>
                    @endif
                    @if($complaint->piece)
                    <dt class="col-sm-4">{{ __('app.complaint_piece') }}</dt>
                    <dd class="col-sm-8"><span class="font-monospace">{{ $complaint->piece->code }}</span> · {{ $complaint->piece->description }}
                        @if($complaint->piece->intake_notes)<div class="text-info-emphasis"><i class="bi bi-info-circle"></i> {{ __('app.piece_intake_notes') }}: {{ $complaint->piece->intake_notes }}</div>@endif
                    </dd>
                    @endif
                    <dt class="col-sm-4">{{ __('app.complaint_channel') }}</dt>
                    <dd class="col-sm-8">{{ __('app.complaint_channel_' . $complaint->channel) }}</dd>
                    <dt class="col-sm-4">{{ __('app.complaint_opened') }}</dt>
                    <dd class="col-sm-8">{{ $complaint->created_at->format(datetime_fmt()) }} · {{ $complaint->opener?->name }}</dd>
                    <dt class="col-sm-4">{{ __('app.complaint_deadline') }}</dt>
                    <dd class="col-sm-8 {{ $complaint->isLate() ? "text-danger fw-semibold" : "" }}">{{ $complaint->due_at->format(date_fmt()) }} @if($complaint->isLate())({{ __('app.complaints_late') }})@endif</dd>
                    @if($complaint->isClosed())
                    <dt class="col-sm-4">{{ __('app.complaint_resolution') }}</dt>
                    <dd class="col-sm-8">{{ $complaint->resolution ? __('app.complaint_resolution_' . $complaint->resolution) : "—" }} · {{ $complaint->resolved_at?->format(date_fmt()) }} · {{ $complaint->resolver?->name }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        @if($complaint->order)
        <form action="{{ route("complaints.attribution", $complaint) }}" method="POST" class="card mb-3">
            @csrf @method("PUT")
            <div class="card-header"><i class="bi bi-diagram-3 me-1"></i> {{ __('app.complaint_attribution') }}</div>
            <div class="card-body row g-2">
                <div class="col-md-4">
                    <label class="form-label small mb-0">{{ __('app.complaint_stage') }}</label>
                    <select name="stage" class="form-select form-select-sm">
                        <option value="">{{ __('app.unknown') }}</option>
                        @foreach(\App\Models\Order::PRODUCTION_STAGES as $st)
                            <option value="{{ $st }}" @selected($complaint->stage === $st)>{{ __('app.status_' . $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-0">{{ __('app.load') }}</label>
                    <select name="machine_load_id" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($orderLoads as $l)
                            <option value="{{ $l->id }}" @selected($complaint->machine_load_id === $l->id)>{{ $l->code }} · {{ $l->equipment?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-0">{{ __('app.operator') }}</label>
                    <select name="employee_id" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->id }}" @selected($complaint->employee_id === $e->id)>{{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <span class="small text-muted">{{ __('app.complaint_attribution_help') }}</span>
                    <button class="btn btn-sm btn-outline-primary">{{ __('app.save') }}</button>
                </div>
            </div>
        </form>
        @endif

        @if($complaint->creditNotes->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-file-earmark-minus me-1"></i> {{ __('app.credit_notes') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($complaint->creditNotes as $n)
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="{{ route("credit-notes.show", $n) }}" target="_blank" class="{{ $n->isVoided() ? "text-decoration-line-through" : "" }}">{{ $n->number }}</a>
                        <span>{{ money((float) $n->amount) }} · {{ $n->isRefund() ? __('app.refund') : __('app.cn_settlement_deduct') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    <div class="col-lg-5">
        <form action="{{ route("complaints.update", $complaint) }}" method="POST" class="card mb-3">
            @csrf @method("PUT")
            <div class="card-header"><i class="bi bi-pencil-square me-1"></i> {{ __('app.complaint_follow_up') }}</div>
            <div class="card-body">
                <div class="mb-2">
                    <label class="form-label small mb-0" for="status">{{ __('app.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        @foreach(\App\Models\Complaint::STATUSES as $st)
                            <option value="{{ $st }}" @selected(old("status", $complaint->status) === $st)>{{ __('app.complaint_status_' . $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0" for="resolution">{{ __('app.complaint_resolution') }}</label>
                    <select id="resolution" name="resolution" class="form-select form-select-sm @error("resolution") is-invalid @enderror">
                        <option value="">—</option>
                        @foreach(\App\Models\Complaint::RESOLUTIONS as $r)
                            <option value="{{ $r }}" @selected(old("resolution", $complaint->resolution) === $r)>{{ __('app.complaint_resolution_' . $r) }}</option>
                        @endforeach
                    </select>
                    @error("resolution") <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0" for="due_at">{{ __('app.complaint_deadline') }}</label>
                    <input type="date" id="due_at" name="due_at" class="form-control form-control-sm" value="{{ old("due_at", $complaint->due_at->toDateString()) }}">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-0" for="resolution_notes">{{ __('app.complaint_notes') }}</label>
                    <textarea id="resolution_notes" name="resolution_notes" rows="3" maxlength="2000" class="form-control form-control-sm" placeholder="{{ __('app.complaint_notes_placeholder') }}">{{ old("resolution_notes", $complaint->resolution_notes) }}</textarea>
                </div>
                <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('app.save') }}</button>
            </div>
        </form>

        @if($complaint->order && !$complaint->isClosed() && auth()->user()->can("create", \App\Models\CreditNote::class))
            <a href="{{ route("orders.credit-notes.create", [$complaint->order_id, "complaint" => $complaint->id]) }}" class="btn btn-outline-danger w-100">
                <i class="bi bi-file-earmark-minus"></i> {{ __('app.complaint_resolve_with_cn') }}
            </a>
            <div class="small text-muted mt-1">{{ __('app.complaint_resolve_with_cn_help') }}</div>
        @endif

        @if($complaint->order && !$complaint->isClosed())
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="mb-1"><i class="bi bi-arrow-counterclockwise me-1"></i> {{ __('app.rework') }}</h6>
                    @if($reworkOrder)
                        <p class="small mb-2">{{ __('app.rework_order_link') }} <a href="{{ route("orders.show", $reworkOrder) }}">{{ $reworkOrder->order_number }}</a> · {{ $reworkOrder->status_label }}</p>
                    @endif
                    @if($complaint->order->abandoned_at === null && $complaint->order->statusRank() >= $complaint->order->statusRank("washing"))
                        <p class="small text-muted mb-2">{{ $complaint->order->status === "completed" ? __('app.rework_new_order_help') : __('app.rework_same_order_help') }}</p>
                        <form action="{{ route("complaints.rework", $complaint) }}" method="POST" data-confirm="{{ __('app.rework_confirm') }}">
                            @csrf
                            <button class="btn btn-outline-warning w-100">{{ $complaint->order->status === "completed" ? __('app.rework_create_order') : __('app.rework_send_back') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
