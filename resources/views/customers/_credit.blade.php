@php($customer = $customer ?? null)
<div class="card mt-3">
    <div class="card-header"><i class="bi bi-credit-card me-1"></i> {{ __('app.credit_settings') }}</div>
    <div class="card-body">
        <div class="form-check form-switch mb-3">
            <input type="hidden" name="is_credit_customer" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_credit_customer" name="is_credit_customer" value="1"
                @checked(old("is_credit_customer", $customer?->is_credit_customer))>
            <label class="form-check-label" for="is_credit_customer">{{ __('app.is_credit_customer') }}</label>
        </div>
        <div id="creditFields" class="{{ old("is_credit_customer", $customer?->is_credit_customer) || $errors->has("credit_limit") ? "" : "d-none" }}" data-show-if="is_credit_customer">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="credit_limit">{{ __('app.credit_limit') }}</label>
                    <input type="number" name="credit_limit" id="credit_limit" class="form-control @error("credit_limit") is-invalid @enderror"
                        value="{{ old("credit_limit", $customer?->credit_limit ?? 0) }}" min="0" step="1000">
                    @error("credit_limit") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">{{ __('app.credit_limit_hint') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="credit_terms_days">{{ __('app.credit_terms_days') }}</label>
                    <input type="number" name="credit_terms_days" id="credit_terms_days" class="form-control @error("credit_terms_days") is-invalid @enderror"
                        value="{{ old("credit_terms_days", $customer?->credit_terms_days ?? 30) }}" min="1" max="365">
                    @error("credit_terms_days") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">{{ __('app.credit_terms_days_hint') }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="billing_cycle">{{ __('app.billing_cycle') }}</label>
                    <select name="billing_cycle" id="billing_cycle" class="form-select @error("billing_cycle") is-invalid @enderror">
                        @foreach(\App\Models\Customer::BILLING_CYCLES as $cycle)
                            <option value="{{ $cycle }}" @selected(old("billing_cycle", $customer?->billing_cycle ?? "per_order") === $cycle)>{{ __('app.billing_cycle_' . $cycle) }}</option>
                        @endforeach
                    </select>
                    @error("billing_cycle") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">{{ __('app.billing_cycle_hint') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

