@extends("layouts.pay")

@section("content")
<form method="POST" action="{{ route('pay.lookup.submit') }}" class="card shadow-sm">
    @csrf
    <div class="card-body p-4">
        <h5 class="mb-1">{{ __('app.pay_title') }}</h5>
        <p class="text-muted small">{{ __('app.pay_lookup_help') }}</p>
        <div class="offscreen" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <label class="form-label">{{ __('app.order_number') }}</label>
        <input type="text" name="order_number" class="form-control mb-2 @error('order_number') is-invalid @enderror" value="{{ old('order_number') }}" required>
        @error('order_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <label class="form-label">{{ __('app.pay_phone_used_at_counter') }}</label>
        <input type="tel" name="phone" class="form-control mb-3" value="{{ old('phone') }}" placeholder="84 000 0000" required>
        <button class="btn btn-success w-100">{{ __('app.continue') }}</button>
    </div>
</form>
@endsection
