@extends("layouts.admin")
@section("title", __('app.ai_settings'))
@section("page_title", __('app.ai_settings'))
@section("page_subtitle", __('app.ai_settings_subtitle'))

@push("styles")
    @vite("resources/css/pages/assistant.css")
@endpush

@section("content")
<x-page-data name="ai-models" :data="$suggestedModels"/>
@include("admin.partials.settings-level", ["bar" => $levelBar, "release" => "admin.ai.release"])

<div class="row g-3 ai-page">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('admin.ai.update') }}" class="card" id="aiSettingsForm">
            @csrf @method('PUT')
            <input type="hidden" name="level" value="{{ $levelParam }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-cpu"></i> {{ __('app.ai_provider') }}</span>
                @if($config->usable())<span class="badge bg-success">{{ __('app.ai_status_active') }}</span>
                @elseif($config->enabled)<span class="badge bg-warning text-dark">{{ __('app.ai_status_incomplete') }}</span>
                @else<span class="badge bg-secondary">{{ __('app.ai_status_off') }}</span>@endif
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="enabled" value="0">
                    <input class="form-check-input" type="checkbox" name="enabled" value="1" id="ai_enabled" @checked(old('enabled', $config->enabled))>
                    <label class="form-check-label fw-semibold" for="ai_enabled">{{ __('app.ai_enabled') }}</label>
                    <div class="form-text">{{ __('app.ai_enabled_help') }}</div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="ai_provider">{{ __('app.ai_provider') }}</label>
                        <select name="provider" id="ai_provider" class="form-select">
                            @foreach($providers as $key => $preset)
                                <option value="{{ $key }}" data-base-url="{{ $preset['base_url'] }}" data-model="{{ $preset['model'] }}" data-needs-key="{{ $preset['key'] ? 1 : 0 }}" data-price-in="{{ $preset['prices'][0] ?? '' }}" data-price-out="{{ $preset['prices'][1] ?? '' }}" @selected(old('provider', $config->provider) === $key)>{{ __('app.ai_provider_' . $key) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ai_model_select">{{ __('app.ai_model') }}</label>
                        <div class="input-group">
                            <select id="ai_model_select" class="form-select" data-other="{{ __('app.ai_model_other') }}" data-suggested="{{ __('app.ai_models_suggested') }}" data-available="{{ __('app.ai_models_available') }}"></select>
                            <button type="button" class="btn btn-outline-secondary" id="aiLoadModels" title="{{ __('app.ai_models_load') }}"
                                data-url="{{ route('admin.ai.models') }}" data-loading="{{ __('app.ai_models_loading') }}" data-loaded="{{ __('app.ai_models_loaded') }}" data-error="{{ __('app.ai_error_generic') }}">
                                <i class="bi bi-arrow-clockwise"></i> <span class="d-none d-xl-inline">{{ __('app.ai_models_load') }}</span>
                            </button>
                        </div>
                        <input type="text" name="model" id="ai_model" class="form-control mt-2 @error('model') is-invalid @enderror" value="{{ old('model', $config->model) }}" maxlength="100" placeholder="{{ __('app.ai_model_other_placeholder') }}">
                        <div class="form-text" id="aiModelsStatus">{{ __('app.ai_model_help') }}</div>
                        @error('model')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ai_api_key">{{ __('app.ai_api_key') }}</label>
                        <input type="password" name="api_key" id="ai_api_key" class="form-control" autocomplete="new-password" maxlength="500" placeholder="{{ $hasKey ? '••••••••' : '' }}">
                        <div class="form-text">
                            {{ $hasKey ? __('app.ai_api_key_saved') : __('app.ai_api_key_help') }}
                        </div>
                        @if($hasKey)
                            <div class="form-check small">
                                <input class="form-check-input" type="checkbox" name="forget_key" value="1" id="ai_forget_key">
                                <label class="form-check-label" for="ai_forget_key">{{ __('app.ai_forget_key') }}</label>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ai_base_url">{{ __('app.ai_base_url') }}</label>
                        <input type="url" name="base_url" id="ai_base_url" class="form-control @error('base_url') is-invalid @enderror" value="{{ old('base_url', $config->baseUrl) }}" maxlength="255" placeholder="https://…/v1">
                        <div class="form-text">{{ __('app.ai_base_url_help') }}</div>
                        @error('base_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <hr>
                <h6 class="fw-semibold">{{ __('app.ai_costs_limits') }}</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small" for="ai_price_input">{{ __('app.ai_price_input') }}</label>
                        <input type="number" step="0.0001" min="0" name="price_input" id="ai_price_input" class="form-control" value="{{ old('price_input', $config->priceInput) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="ai_price_output">{{ __('app.ai_price_output') }}</label>
                        <input type="number" step="0.0001" min="0" name="price_output" id="ai_price_output" class="form-control" value="{{ old('price_output', $config->priceOutput) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="ai_monthly_budget">{{ __('app.ai_monthly_budget') }}</label>
                        <input type="number" step="0.01" min="0" name="monthly_budget" id="ai_monthly_budget" class="form-control" value="{{ old('monthly_budget', $config->monthlyBudget ?: '') }}" placeholder="{{ __('app.ai_no_limit') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="ai_daily_questions">{{ __('app.ai_daily_questions') }}</label>
                        <input type="number" min="0" name="daily_questions" id="ai_daily_questions" class="form-control" value="{{ old('daily_questions', $config->dailyQuestions) }}">
                    </div>
                </div>
                <div class="form-text">{{ __('app.ai_prices_help') }}</div>

                <div class="form-check form-switch mt-3">
                    <input type="hidden" name="mask_personal" value="0">
                    <input class="form-check-input" type="checkbox" name="mask_personal" value="1" id="ai_mask_personal" @checked(old('mask_personal', $config->maskPersonal))>
                    <label class="form-check-label" for="ai_mask_personal">{{ __('app.ai_mask_personal') }}</label>
                    <div class="form-text">{{ __('app.ai_mask_personal_help') }}</div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-plug"></i> {{ __('app.ai_test_connection') }}</div>
            <div class="card-body small">
                <p class="text-muted">{{ __('app.ai_test_help') }}</p>
                <form method="POST" action="{{ route('admin.ai.test') }}">
                    @csrf <input type="hidden" name="level" value="{{ $levelParam }}">
                    <button class="btn btn-outline-primary btn-sm" @disabled(!$config->complete())><i class="bi bi-lightning"></i> {{ __('app.ai_test_connection') }}</button>
                </form>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-graph-up"></i> {{ __('app.ai_usage_month') }}</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.ai_calls') }}</span><strong>{{ number_format($usage['runs']) }}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.ai_tokens_in_out') }}</span><strong>{{ number_format($usage['input_tokens']) }} / {{ number_format($usage['output_tokens']) }}</strong></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.ai_cost_estimate') }}</span><strong>US$ {{ number_format($usage['cost'], 2) }}@if($config->monthlyBudget > 0) / {{ number_format($config->monthlyBudget, 2) }}@endif</strong></li>
            </ul>
        </div>
        <div class="alert alert-info small"><i class="bi bi-shield-check"></i> {{ __('app.ai_privacy_note') }}</div>
    </div>
</div>
@endsection

@push("scripts")
    @vite("resources/js/pages/ai-settings.js")
@endpush
