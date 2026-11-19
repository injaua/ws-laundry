@extends("layouts.admin")
@section("title", __('app.system_settings_localization'))
@section("page_title", __('app.system_settings') . ' · ' . __('app.system_settings_localization'))

@section("content")
<form action="{{ route('admin.system-settings.localization.update') }}" method="POST">
    @csrf
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-translate me-1"></i> {{ __('app.language_and_region') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="locale_default">{{ __('app.default_language') }}</label>
                        <select name="locale_default" id="locale_default" class="form-select">
                            @foreach($languages as $lang)
                                <option value="{{ $lang->code }}" @selected(old('locale_default', $settings['locale_default']) === $lang->code)>{{ $lang->label() }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('app.default_language_help') }} <a href="{{ route('admin.languages.index') }}">{{ __('app.manage_languages') }}</a></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="timezone">{{ __('app.default_timezone') }}</label>
                        <select name="timezone" id="timezone" class="form-select @error('timezone') is-invalid @enderror">
                            @foreach($timezones as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', $settings['timezone']) === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('app.default_timezone_help', ['now' => now()->format(datetime_fmt())]) }}</div>
                        @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label" for="date_format">{{ __('app.date_format') }}</label>
                            <select name="date_format" id="date_format" class="form-select">
                                @foreach($examples['date'] as $fmt => $example)
                                    <option value="{{ $fmt }}" @selected(old('date_format', $settings['date_format']) === $fmt)>{{ $example }} ({{ $fmt }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="time_format">{{ __('app.time_format') }}</label>
                            <select name="time_format" id="time_format" class="form-select">
                                @foreach($examples['time'] as $fmt => $example)
                                    <option value="{{ $fmt }}" @selected(old('time_format', $settings['time_format']) === $fmt)>{{ $example }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label d-block">{{ __('app.text_direction') }}</label>
                            @foreach(['ltr' => __('app.text_direction_ltr'), 'rtl' => __('app.text_direction_rtl')] as $dir => $label)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="text_direction" value="{{ $dir }}" id="dir_{{ $dir }}" @checked(old('text_direction', $settings['text_direction']) === $dir)>
                                    <label class="form-check-label" for="dir_{{ $dir }}">{{ $label }}</label>
                                </div>
                            @endforeach
                            <div class="form-text">{{ __('app.text_direction_help') }}</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="phone_country_code">{{ __('app.phone_country_code') }}</label>
                            <input type="text" name="phone_country_code" id="phone_country_code" maxlength="5" class="form-control @error('phone_country_code') is-invalid @enderror" value="{{ old('phone_country_code', $settings['phone_country_code']) }}">
                            <div class="form-text">{{ __('app.phone_country_code_help') }}</div>
                            @error('phone_country_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cash-stack me-1"></i> {{ __('app.currency_and_numbers') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="currency_code">{{ __('app.default_currency') }}</label>
                        <select name="currency_code" id="currency_code" class="form-select @error('currency_code') is-invalid @enderror">
                            @foreach($currencies as $cur)
                                <option value="{{ $cur->code }}" data-symbol="{{ $cur->symbol }}" data-position="{{ $cur->position }}" data-decimals="{{ $cur->decimals }}" @selected(old('currency_code', $settings['currency_code']) === $cur->code)>{{ $cur->code }} · {{ $cur->name }} ({{ $cur->symbol }})</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('app.default_currency_help') }} <a href="{{ route('admin.currencies.index') }}">{{ __('app.manage_currencies') }}</a></div>
                        @error('currency_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="number_format">{{ __('app.number_format') }}</label>
                        <select name="number_format" id="number_format" class="form-select">
                            @foreach($examples['number'] as $key => $example)
                                <option value="{{ $key }}" @selected(old('number_format', $settings['number_format']) === $key)>{{ $example }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="alert alert-light border mb-0 small">
                        {{ __('app.price_preview') }}: <strong id="money-preview">{{ money(1234567.5) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
    </div>
</form>

@push('scripts')
    <x-page-data name="money-format" :data="['formats' => \App\Support\SystemSettings::NUMBER_FORMATS]"/>
    @vite("resources/js/pages/money-format-preview.js")
@endpush
@endsection
