@extends("layouts.pay")

@section("content")
<div class="card shadow-sm">
    <div class="card-body p-4 text-center">
        <div id="icon" class="display-4 mb-2">
            @if($tx->isOpen())
                <div class="spinner-border text-success square-3rem"></div>
            @elseif($tx->isSuccessful())
                <i class="bi bi-check-circle-fill text-success"></i>
            @else
                <i class="bi bi-x-circle-fill text-danger"></i>
            @endif
        </div>
        <h5 id="title">
            @if($tx->isOpen()) {{ __('app.pay_confirm_on_phone') }}
            @elseif($tx->isSuccessful()) {{ __('app.pay_thank_you') }}
            @else {{ __('app.mm_public_failed') }} @endif
        </h5>
        <p class="text-muted" id="hint">
            @if($tx->isOpen()) {{ __('app.pay_pin_sent', ['phone' => '+' . substr($tx->phone, 0, 5) . '***' . substr($tx->phone, -3), 'provider' => __('app.' . $tx->provider)]) }}
            @elseif($tx->isSuccessful()) {{ __('app.pay_success_detail', ['amount' => money((float) $tx->amount), 'order' => $tx->order?->order_number]) }}
            @endif
        </p>
        @if(!$tx->isOpen() && !$tx->isSuccessful() && $backUrl)
            <a href="{{ $backUrl }}" class="btn btn-outline-success">{{ __('app.pay_try_again') }}</a>
        @endif
    </div>
</div>
@endsection

@if($tx->isOpen())
@push("scripts")
    <span hidden data-poll-reload="{{ $statusUrl }}" data-poll-max="100"></span>
@endpush
@endif
