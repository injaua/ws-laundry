@extends("layouts.admin")
@section("title", __('app.languages'))
@section("page_title", $language->exists ? __('app.language_edit', ['name' => $language->name]) : __('app.language_new'))

@section("content")
<div class="row">
    <div class="col-lg-7">
        <form method="POST" action="{{ $language->exists ? route('admin.languages.update', $language) : route('admin.languages.store') }}" class="card">
            @csrf
            @if($language->exists) @method('PUT') @endif
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">{{ __('app.name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" maxlength="60" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $language->name) }}" placeholder="Changana">
                        <div class="form-text">{{ __('app.language_name_help') }}</div>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="code">{{ __('app.code') }} <span class="text-danger">*</span></label>
                        @if($language->exists)
                            <input type="text" id="code" class="form-control font-monospace" value="{{ $language->code }}" disabled>
                        @else
                            <input type="text" name="code" id="code" maxlength="10" required class="form-control font-monospace @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="ts">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @endif
                        <div class="form-text">{{ __('app.language_code_help') }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="flag">{{ __('app.flag') }}</label>
                        <input type="text" name="flag" id="flag" maxlength="16" class="form-control @error('flag') is-invalid @enderror" value="{{ old('flag', $language->flag) }}" placeholder="🇲🇿">
                    </div>
                    @unless($language->is_system)
                    <div class="col-md-6">
                        <label class="form-label" for="base_locale">{{ __('app.language_base') }}</label>
                        <select name="base_locale" id="base_locale" class="form-select">
                            @foreach(\App\Models\Language::BASES as $base)
                                <option value="{{ $base }}" @selected(old('base_locale', $language->base_locale) === $base)>{{ \App\Support\Languages::find($base)?->label() ?? $base }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('app.language_base_help') }}</div>
                    </div>
                    @endunless
                    <div class="col-md-6">
                        <label class="form-label d-block">{{ __('app.text_direction') }}</label>
                        @foreach(['ltr' => __('app.text_direction_ltr'), 'rtl' => __('app.text_direction_rtl')] as $dir => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="direction" value="{{ $dir }}" id="dir_{{ $dir }}" @checked(old('direction', $language->direction) === $dir)>
                                <label class="form-check-label" for="dir_{{ $dir }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $language->is_active))>
                            <label class="form-check-label" for="is_active">{{ __('app.language_active_help') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('admin.languages.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                <button class="btn btn-primary"><i class="bi bi-save"></i> {{ $language->exists ? __('app.save') : __('app.language_create_and_translate') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
