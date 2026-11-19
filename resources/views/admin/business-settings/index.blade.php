@extends("layouts.admin")
@section("title", __('app.business_settings'))
@section("page_title", __('app.business_settings'))

@section("content")
<form action="{{ route("admin.business-settings.update") }}" method="POST">
    @csrf
    <div class="alert alert-light border small">
        <i class="bi bi-building me-1"></i> {{ __('app.company_identity_moved') }}
        <a href="{{ route('admin.system-settings.general') }}">{{ __('app.system_settings') }} → {{ __('app.system_settings_general') }}</a>
    </div>
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-percent me-1"></i> {{ __('app.fiscal_settings') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.vat_rate') }}</label>
                        <div class="input-group mw-220px">
                            <input type="number" name="vat_rate" step="0.5" min="0" max="100" class="form-control @error("vat_rate") is-invalid @enderror"
                                value="{{ $settings["vat_rate"] ?? 16 }}">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">{{ __('app.vat_settings_hint') }}</div>
                        @error("vat_rate") <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            @php($expressType = old("express_surcharge_type", $settings["express_surcharge_type"] ?? "percent"))
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-lightning-charge me-1"></i> {{ __('app.express_service') }}</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="express_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="express_enabled" name="express_enabled" value="1" @checked(old("express_enabled", $settings["express_enabled"] ?? false))>
                        <label class="form-check-label" for="express_enabled">{{ __('app.express_enabled') }}</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-7">
                            <label class="form-label" for="express_surcharge_value">{{ __('app.express_surcharge') }}</label>
                            <div class="input-group">
                                <select name="express_surcharge_type" class="form-select mw-150px">
                                    @foreach(["percent", "fixed"] as $t)
                                        <option value="{{ $t }}" @selected($expressType === $t)>{{ __('app.express_type_' . $t, ['currency' => currency_symbol()]) }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="express_surcharge_value" id="express_surcharge_value" min="0" step="0.5" class="form-control @error("express_surcharge_value") is-invalid @enderror"
                                    value="{{ old("express_surcharge_value", $settings["express_surcharge_value"] ?? 50) }}">
                            </div>
                            @error("express_surcharge_value") <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label" for="express_lead_days">{{ __('app.express_lead_days') }}</label>
                            <input type="number" name="express_lead_days" id="express_lead_days" min="0" max="30" class="form-control @error("express_lead_days") is-invalid @enderror"
                                value="{{ old("express_lead_days", $settings["express_lead_days"] ?? 1) }}">
                            @error("express_lead_days") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="form-text mt-2">{{ __('app.express_settings_hint') }}</div>
                </div>
            </div>

        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-kanban me-1"></i> {{ __('app.production') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="production_capacity_kg">{{ __('app.capacity_kg_per_day') }}</label>
                            <input type="number" name="production_capacity_kg" id="production_capacity_kg" min="0" step="0.5" class="form-control @error("production_capacity_kg") is-invalid @enderror"
                                value="{{ old("production_capacity_kg", $settings["production_capacity_kg"] ?? "") }}">
                            @error("production_capacity_kg") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="production_capacity_pieces">{{ __('app.capacity_pieces_per_day') }}</label>
                            <input type="number" name="production_capacity_pieces" id="production_capacity_pieces" min="0" class="form-control @error("production_capacity_pieces") is-invalid @enderror"
                                value="{{ old("production_capacity_pieces", $settings["production_capacity_pieces"] ?? "") }}">
                            @error("production_capacity_pieces") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="form-text mb-3">{{ __('app.capacity_hint') }}</div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="qc_required" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="qc_required" name="qc_required" value="1" @checked(old("qc_required", $settings["qc_required"] ?? false))>
                        <label class="form-check-label" for="qc_required">{{ __('app.qc_required_setting') }}</label>
                    </div>
                    <div class="form-text">{{ __('app.qc_required_hint') }}</div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-cash-coin me-1"></i> {{ __('app.payment_settings') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="cheque_policy">{{ __('app.cheque_policy') }}</label>
                            <select name="cheque_policy" id="cheque_policy" class="form-select">
                                @foreach(\App\Models\Cheque::POLICIES as $policy)
                                    <option value="{{ $policy }}" @selected(old("cheque_policy", \App\Models\Cheque::policy()) === $policy)>{{ __('app.cheque_policy_' . $policy) }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('app.cheque_policy_hint') }}</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="cash_till_limit">{{ __('app.cash_till_limit') }}</label>
                            <div class="input-group">
                                <input type="number" name="cash_till_limit" id="cash_till_limit" min="0" step="100" class="form-control @error("cash_till_limit") is-invalid @enderror"
                                    value="{{ old("cash_till_limit", $settings["cash_till_limit"] ?? "") }}">
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                                @error("cash_till_limit") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text">{{ __('app.cash_till_limit_hint') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i> {{ __('app.save') }}
            </button>
        </div>
    </div>
</form>
@endsection
