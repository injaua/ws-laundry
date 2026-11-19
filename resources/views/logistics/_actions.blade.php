{{-- Road actions on one trip: start, done (with cash collection on deliveries), failed. Expects: $job. --}}
@php
    $toCollect = $job->amountToCollect();
    $mustPayAll = $job->isDelivery() && $job->order && !$job->order->is_credit;
@endphp
@if($job->isActionable())
<div class="d-flex flex-wrap gap-2">
    @if($job->status === "scheduled")
    <form action="{{ route("logistics.start", $job) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-signpost-2"></i> {{ __('app.courier_start') }}</button>
    </form>
    @endif
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="collapse" data-bs-target="#done{{ $job->id }}">
        <i class="bi bi-check2-circle"></i> {{ $job->isPickup() ? __('app.mark_picked_up') : __('app.mark_delivered') }}
    </button>
    @if($job->isDelivery() && $job->order && $job->order->remainingBalance() > 0 && \App\Services\MobileMoney\MobileMoneySettings::enabledProviders() !== [])
    <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="collapse" data-bs-target="#mm{{ $job->id }}">
        <i class="bi bi-phone"></i> {{ __('app.mm_charge_short') }}
    </button>
    @endif
    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#fail{{ $job->id }}">
        <i class="bi bi-x-circle"></i> {{ __('app.mark_failed') }}
    </button>
</div>

@if($job->isDelivery() && $job->order && $job->isActionable() && $job->order->remainingBalance() > 0 && \App\Services\MobileMoney\MobileMoneySettings::enabledProviders() !== [])
<div class="collapse mt-2" id="mm{{ $job->id }}">
    @include("mobile-payments._charge", ["order" => $job->order, "job" => $job])
</div>
@endif
<div class="collapse mt-2" id="done{{ $job->id }}">
    <form action="{{ route("logistics.complete", $job) }}" method="POST" class="border rounded p-2 bg-body-tertiary">
        @csrf
        @if($job->isDelivery())
            @if($toCollect > 0)
            <div class="row g-2 mb-2">
                <div class="col-7">
                    <label class="form-label small mb-1" for="amount{{ $job->id }}">{{ __('app.amount_collected') }} ({{ currency_symbol() }})</label>
                    <input type="number" name="amount" id="amount{{ $job->id }}" min="0" max="{{ $toCollect }}" step="0.01" class="form-control form-control-sm" value="{{ $toCollect }}" @if($mustPayAll) readonly @endif>
                </div>
                <div class="col-5">
                    <label class="form-label small mb-1" for="method{{ $job->id }}">{{ __('app.payment_method') }}</label>
                    <select name="method" id="method{{ $job->id }}" class="form-select form-select-sm">
                        @foreach(\App\Models\Payment::courierMethods() as $m)
                            <option value="{{ $m }}" @selected($m === "cash")>{{ __('app.' . $m) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @include("payments._mobile-ref", ["sm" => true, "phone" => $job->order?->customer?->phone])
            <div class="form-text mb-2">{{ $mustPayAll ? __('app.delivery_collect_all_hint') : __('app.delivery_collect_credit_hint') }}</div>
            @else
            <div class="small text-success mb-2"><i class="bi bi-check-circle"></i> {{ __('app.nothing_to_collect') }}</div>
            @endif
        @endif
        <input type="text" name="outcome_notes" maxlength="500" class="form-control form-control-sm mb-2" placeholder="{{ $job->isPickup() ? __('app.pickup_notes_placeholder') : __('app.delivery_notes_placeholder') }}">
        <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-check2"></i> {{ __('app.confirm') }}</button>
    </form>
</div>

<div class="collapse mt-2" id="fail{{ $job->id }}">
    <form action="{{ route("logistics.fail", $job) }}" method="POST" class="border rounded p-2 bg-body-tertiary">
        @csrf
        <select name="failure_reason" class="form-select form-select-sm mb-2" required>
            @foreach(["customer_absent", "wrong_address", "customer_refused", "no_payment", "other"] as $reason)
                <option value="{{ __('app.fail_reason_' . $reason) }}">{{ __('app.fail_reason_' . $reason) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-danger btn-sm w-100">{{ __('app.mark_failed') }}</button>
    </form>
</div>
@endif
