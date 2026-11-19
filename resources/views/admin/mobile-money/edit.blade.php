@extends("layouts.admin")
@section("title", __('app.mm_settings'))
@section("page_title", __('app.mm_settings'))
@section("page_subtitle", __('app.mm_settings_subtitle'))

@php($labels = ["api_key" => "API Key", "public_key" => "Public Key", "service_provider_code" => "Service Provider Code", "origin" => "Origin", "partner_code" => "Partner code", "username" => "Username", "password" => "Password", "sandbox_url" => "Sandbox URL", "live_url" => "Live URL"])

@section("content")
@include("admin.partials.settings-level", ["bar" => $levelBar, "release" => "admin.mobile-money.release"])

{{-- Methods accepted for new payments at this level --}}
<form method="POST" action="{{ route('admin.mobile-money.methods') }}" class="card mb-3">
    @csrf @method('PUT')
    <input type="hidden" name="level" value="{{ $levelParam }}">
    <div class="card-header fw-semibold"><i class="bi bi-wallet2 me-1"></i> {{ __('app.payment_methods_accepted') }}</div>
    <div class="card-body">
        <p class="small text-muted mb-2">{{ __('app.payment_methods_accepted_help') }}</p>
        <div class="d-flex flex-wrap gap-3">
            @foreach($methods as $method)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="methods[]" value="{{ $method }}" id="method_{{ $method }}" @checked(in_array($method, old('methods', $enabledMethods), true))>
                    <label class="form-check-label" for="method_{{ $method }}">{{ __('app.' . $method) }}</label>
                </div>
            @endforeach
        </div>
        @error('methods') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>
    <div class="card-footer text-end">
        <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
    </div>
</form>

<div class="alert alert-info small"><i class="bi bi-info-circle"></i> {{ __('app.mm_settings_help') }}</div>
<div class="row g-3">
    @foreach($providers as $provider => $p)
        <div class="col-lg-6">
            <form method="POST" action="{{ route('admin.mobile-money.update', $provider) }}" class="card h-100">
                @csrf @method('PUT')
                <input type="hidden" name="level" value="{{ $levelParam }}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">{{ __('app.' . $provider) }}</span>
                    @if($p['mode'] === 'live')<span class="badge bg-success">{{ __('app.mm_mode_live') }}</span>
                    @elseif($p['mode'] === 'off')<span class="badge bg-secondary">{{ __('app.mm_mode_off') }}</span>
                    @else<span class="badge bg-warning text-dark">{{ __('app.mm_mode_' . $p['mode']) }}</span>@endif
                </div>
                <div class="card-body">
                    <label class="form-label">{{ __('app.mm_mode') }}</label>
                    <select name="mode" class="form-select mb-1">
                        @foreach(\App\Services\MobileMoney\MobileMoneySettings::MODES as $mode)
                            <option value="{{ $mode }}" @selected($p['mode'] === $mode)>{{ __('app.mm_mode_' . $mode) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text mb-3">{{ __('app.mm_mode_help') }}</div>
                    @unless(in_array($provider, $enabledMethods, true))
                        <div class="alert alert-warning py-1 px-2 small">{{ __('app.mm_method_not_accepted') }}</div>
                    @endunless

                    @foreach(\App\Services\MobileMoney\MobileMoneySettings::FIELDS[$provider] as $field => $secret)
                        <label class="form-label small mb-0" for="{{ $provider }}_{{ $field }}">{{ $labels[$field] ?? $field }}</label>
                        @if($field === 'public_key')
                            <textarea name="{{ $field }}" id="{{ $provider }}_{{ $field }}" rows="3" class="form-control form-control-sm mb-2 font-monospace" placeholder="{{ $p['secrets'][$field] ?? false ? __('app.mm_secret_kept') : '' }}"></textarea>
                        @else
                            <input type="{{ $secret ? 'password' : 'text' }}" name="{{ $field }}" id="{{ $provider }}_{{ $field }}" autocomplete="off"
                                class="form-control form-control-sm mb-2 @error($field) is-invalid @enderror"
                                value="{{ $secret ? '' : old($field, $p['settings'][$field] ?? '') }}"
                                placeholder="{{ $secret && ($p['secrets'][$field] ?? false) ? __('app.mm_secret_kept') : ($field === 'origin' ? 'developer.mpesa.vm.co.mz' : '') }}">
                        @endif
                    @endforeach
                    @if($provider === 'emola')
                        <div class="form-text">{{ __('app.mm_emola_help') }}</div>
                    @endif
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span class="small {{ $p['configured'] ? 'text-success' : 'text-muted' }}">
                        <i class="bi bi-{{ $p['configured'] ? 'check-circle' : 'dash-circle' }}"></i> {{ $p['configured'] ? __('app.mm_credentials_ok') : __('app.mm_credentials_missing') }}
                    </span>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
                </div>
            </form>
        </div>
    @endforeach
</div>
@endsection
