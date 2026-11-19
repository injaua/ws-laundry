@php($customer = $customer ?? null)
@php($isBusiness = old("type", $customer?->type ?? "individual") === "business")
<div class="card mt-3">
    <div class="card-header"><i class="bi bi-building me-1"></i> {{ __('app.business_details') }}</div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label d-block">{{ __('app.customer_type') }}</label>
            @foreach(\App\Models\Customer::TYPES as $type)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="type" id="type_{{ $type }}" value="{{ $type }}"
                        @checked(old("type", $customer?->type ?? "individual") === $type)>
                    <label class="form-check-label" for="type_{{ $type }}">{{ __('app.customer_type_' . $type) }}</label>
                </div>
            @endforeach
        </div>

        <div id="businessFields" class="{{ $isBusiness ? "" : "d-none" }}" data-show-if="type=business">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="legal_name">{{ __('app.legal_name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="legal_name" id="legal_name" maxlength="150" class="form-control @error("legal_name") is-invalid @enderror"
                        value="{{ old("legal_name", $customer?->legal_name) }}">
                    @error("legal_name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="nuit">{{ __('app.nit') }} <span class="text-danger">*</span></label>
                    <input type="text" name="nuit" id="nuit" inputmode="numeric" maxlength="9" class="form-control @error("nuit") is-invalid @enderror"
                        value="{{ old("nuit", $customer?->nuit) }}" placeholder="400000000">
                    @error("nuit") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">{{ __('app.nuit_hint') }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contact_person">{{ __('app.contact_person') }}</label>
                    <input type="text" name="contact_person" id="contact_person" maxlength="100" class="form-control @error("contact_person") is-invalid @enderror"
                        value="{{ old("contact_person", $customer?->contact_person) }}">
                    @error("contact_person") <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">{{ __('app.billing_email') }}</label>
                    <input type="email" name="email" id="email" maxlength="150" class="form-control @error("email") is-invalid @enderror"
                        value="{{ old("email", $customer?->email) }}">
                    @error("email") <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="billing_address">{{ __('app.billing_address') }}</label>
                    <textarea name="billing_address" id="billing_address" rows="2" class="form-control @error("billing_address") is-invalid @enderror"
                        placeholder="{{ __('app.billing_address_hint') }}">{{ old("billing_address", $customer?->billing_address) }}</textarea>
                    @error("billing_address") <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        @unless(auth()->user()->isCashier())
        <div class="mt-3">
            <label class="form-label" for="discount_percent">{{ __('app.customer_discount') }}</label>
            <div class="input-group mw-180px">
                <input type="number" name="discount_percent" id="discount_percent" min="0" max="100" step="0.5"
                    class="form-control @error("discount_percent") is-invalid @enderror"
                    value="{{ old("discount_percent", (float) ($customer?->discount_percent ?? 0)) }}">
                <span class="input-group-text">%</span>
            </div>
            @error("discount_percent") <div class="text-danger small">{{ $message }}</div> @enderror
            <div class="form-text">{{ __('app.customer_discount_hint') }}</div>
        </div>
        @endunless
    </div>
</div>

