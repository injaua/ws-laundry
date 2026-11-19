{{-- One payment spread over several credit orders, oldest first. Expects: $action, $outstanding, $hint, $customer. --}}
<div class="card h-100">
    <div class="card-header"><i class="bi bi-bank me-1"></i> {{ __('app.record_grouped_payment') }}</div>
    <div class="card-body">
        @if($outstanding <= 0)
            <p class="text-success mb-0"><i class="bi bi-check-circle"></i> {{ __('app.nothing_outstanding') }}</p>
        @else
        <p class="small text-muted">{{ $hint }}</p>
        <form action="{{ $action }}" method="POST" class="row g-2">
            @csrf
            <div class="col-sm-6">
                <label class="form-label small" for="gp_amount">{{ __('app.amount') }} ({{ currency_symbol() }})</label>
                <input type="number" name="amount" id="gp_amount" min="1" max="{{ $outstanding }}" step="0.01"
                    class="form-control @error("amount") is-invalid @enderror" value="{{ old("amount", $outstanding) }}" required>
                @error("amount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ __('app.total_outstanding') }}: {{ money($outstanding) }}</div>
            </div>
            <div class="col-sm-6">
                <label class="form-label small" for="gp_paid_at">{{ __('app.payment_date') }}</label>
                <input type="date" name="paid_at" id="gp_paid_at" class="form-control @error("paid_at") is-invalid @enderror" value="{{ old("paid_at", date("Y-m-d")) }}" required>
                @error("paid_at") <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-sm-6">
                <label class="form-label small" for="gp_method">{{ __('app.payment_method') }}</label>
                <select name="method" id="gp_method" class="form-select" required>
                    @foreach(\App\Models\Payment::formMethods($customer ?? null) as $m)
                        <option value="{{ $m }}" @selected(old("method", "transfer") === $m)>{{ __('app.' . $m) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">@include("payments._mobile-ref", ["sm" => false])</div>
            <div class="col-sm-6">
                <label class="form-label small" for="gp_note">{{ __('app.payment_reference') }}</label>
                <input type="text" name="note" id="gp_note" maxlength="255" class="form-control" value="{{ old("note") }}" placeholder="{{ __('app.payment_reference_hint') }}">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-success w-100"><i class="bi bi-check2-circle"></i> {{ __('app.record_payment') }}</button>
            </div>
        </form>
        @endif
    </div>
</div>
