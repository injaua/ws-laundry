@extends("layouts.pay")
@section("title", __('app.pay_title') . " " . $order->order_number)

@section("content")
<div class="card shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between">
            <span class="text-muted small">{{ __('app.order') }}</span>
            <span class="fw-semibold">{{ $order->order_number }}</span>
        </div>
        <div class="d-flex justify-content-between">
            <span class="text-muted small">{{ __('app.customer') }}</span>
            <span>{{ $order->customer?->name }}</span>
        </div>
        <div class="d-flex justify-content-between">
            <span class="text-muted small">{{ __('app.status') }}</span>
            <span>{{ $order->status_label }}</span>
        </div>
        <hr>
        @if($remaining <= 0)
            <div class="text-center py-3">
                <i class="bi bi-check-circle-fill text-success display-5"></i>
                <h5 class="mt-2">{{ __('app.pay_nothing_due') }}</h5>
            </div>
        @elseif($providers === [])
            <div class="alert alert-warning mb-0">{{ __('app.pay_unavailable') }}</div>
        @else
            <div class="text-center mb-3">
                <div class="text-muted small">{{ __('app.pay_amount_due') }}</div>
                <div class="display-6 fw-bold">{{ money($remaining) }}</div>
            </div>
            <form method="POST" action="{{ $requestUrl }}">
                @csrf
                <label class="form-label">{{ __('app.pay_choose_wallet') }}</label>
                <div class="d-flex gap-2 mb-3">
                    @foreach($providers as $i => $p)
                        <input type="radio" class="btn-check" name="provider" id="p_{{ $p }}" value="{{ $p }}" @checked(old('provider', \App\Models\MobileTransaction::providerForPhone($phone) ?? $providers[0]) === $p) required>
                        <label class="btn btn-outline-success flex-fill" for="p_{{ $p }}">{{ __('app.' . $p) }}</label>
                    @endforeach
                </div>
                <label class="form-label">{{ __('app.pay_wallet_number') }}</label>
                <input type="tel" name="phone" class="form-control mb-1 @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="84 000 0000" required>
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
                @error('provider') <div class="text-danger small">{{ $message }}</div> @enderror
                <div class="form-text mb-3">{{ __('app.pay_pin_help') }}</div>
                <button class="btn btn-success btn-lg w-100"><i class="bi bi-phone"></i> {{ __('app.pay_now') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
