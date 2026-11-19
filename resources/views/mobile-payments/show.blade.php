@extends("layouts.admin")
@section("title", __('app.mm_request') . " " . $tx->reference)
@section("page_title", ($tx->type === 'b2c' ? __('app.mm_refund') : __('app.mm_request')) . " · " . __('app.' . $tx->provider))
@section("page_subtitle", $tx->reference)

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body text-center py-4" id="mm-box">
                <div id="mm-icon" class="display-4 mb-2">
                    @if($tx->isOpen())
                        <div class="spinner-border text-success square-3rem" role="status"></div>
                    @elseif($tx->isSuccessful())
                        <i class="bi bi-check-circle-fill text-success"></i>
                    @else
                        <i class="bi bi-x-circle-fill text-danger"></i>
                    @endif
                </div>
                <h4 id="mm-status" class="mb-1">{{ __('app.mm_status_' . $tx->status) }}</h4>
                <p id="mm-hint" class="text-muted mb-3">
                    @if($tx->isOpen())
                        {{ $tx->type === 'b2c' ? __('app.mm_refund_wait') : __('app.mm_wait_pin', ['phone' => $tx->phone]) }}
                    @else
                        {{ $tx->response_message }}
                    @endif
                </p>
                <div class="fs-3 fw-bold">{{ money((float) $tx->amount) }}</div>
                <dl class="row small text-start mt-3 mb-0">
                    <dt class="col-5">{{ __('app.order') }}</dt>
                    <dd class="col-7">@if($tx->order)<a href="{{ route('orders.show', $tx->order) }}">{{ $tx->order->order_number }}</a> · {{ $tx->order->customer?->name }}@else — @endif</dd>
                    <dt class="col-5">{{ __('app.phone') }}</dt><dd class="col-7">+{{ $tx->phone }}</dd>
                    <dt class="col-5">{{ __('app.transaction_ref') }}</dt><dd class="col-7 font-monospace" id="mm-ref">{{ $tx->provider_ref ?? '—' }}</dd>
                    <dt class="col-5">{{ __('app.mm_our_reference') }}</dt><dd class="col-7 font-monospace">{{ $tx->reference }}</dd>
                    <dt class="col-5">{{ __('app.mm_channel') }}</dt><dd class="col-7">{{ __('app.mm_channel_' . $tx->channel) }} · {{ $tx->user?->name ?? __('app.customer') }}</dd>
                    <dt class="col-5">{{ __('app.date') }}</dt><dd class="col-7">{{ $tx->created_at->format('d/m/Y H:i:s') }}</dd>
                    @if($tx->response_code)
                        <dt class="col-5">{{ __('app.mm_operator_answer') }}</dt><dd class="col-7 small">{{ $tx->response_code }} · {{ $tx->response_message }}</dd>
                    @endif
                    @if($tx->creditNote)
                        <dt class="col-5">{{ __('app.credit_note') }}</dt><dd class="col-7">{{ $tx->creditNote->number }}</dd>
                    @endif
                </dl>
            </div>
            <div class="card-footer d-flex gap-2 justify-content-between">
                @if($tx->order)
                    <a href="{{ route('orders.show', $tx->order) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.order') }}</a>
                @endif
                <span class="d-flex gap-2">
                    @if($tx->payment)
                        <a href="{{ route('payments.receipt', $tx->payment) }}" target="_blank" class="btn btn-outline-primary" id="mm-receipt"><i class="bi bi-printer"></i> {{ __('app.print_receipt') }}</a>
                    @endif
                    @if($tx->isOpen())
                        <form method="POST" action="{{ route('mobile-payments.refresh', $tx) }}">@csrf<button class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> {{ __('app.mm_check_now') }}</button></form>
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>
@endsection

@if($tx->isOpen())
@push("scripts")
    <span hidden data-poll-reload="{{ route('mobile-payments.status', $tx) }}" data-poll-max="120"></span>
@endpush
@endif
