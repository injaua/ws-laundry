@extends("layouts.admin")
@section("title", __('app.currencies'))
@section("page_title", $currency->exists ? __('app.currency_edit', ['code' => $currency->code]) : __('app.currency_new'))

@section("content")
<div class="row">
    <div class="col-lg-6">
        <form method="POST" action="{{ $currency->exists ? route('admin.currencies.update', $currency) : route('admin.currencies.store') }}" class="card">
            @csrf
            @if($currency->exists) @method('PUT') @endif
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="code">{{ __('app.currency_code') }} <span class="text-danger">*</span></label>
                        @if($currency->exists)
                            <input type="text" id="code" class="form-control font-monospace" value="{{ $currency->code }}" disabled>
                        @else
                            <input type="text" name="code" id="code" maxlength="3" required class="form-control font-monospace text-uppercase @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="USD" data-uppercase>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @endif
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="name">{{ __('app.name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" maxlength="60" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $currency->name) }}">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="symbol">{{ __('app.currency_symbol') }} <span class="text-danger">*</span></label>
                        <input type="text" name="symbol" id="symbol" maxlength="10" required class="form-control @error('symbol') is-invalid @enderror" value="{{ old('symbol', $currency->symbol) }}">
                        @error('symbol') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="decimals">{{ __('app.currency_decimals') }}</label>
                        <select name="decimals" id="decimals" class="form-select">
                            @foreach([0, 1, 2, 3] as $d)
                                <option value="{{ $d }}" @selected((int) old('decimals', $currency->decimals) === $d)>{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="position">{{ __('app.currency_position') }}</label>
                        <select name="position" id="position" class="form-select">
                            <option value="before" @selected(old('position', $currency->position) === 'before')>{{ __('app.currency_before_short') }}</option>
                            <option value="after" @selected(old('position', $currency->position) === 'after')>{{ __('app.currency_after_short') }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $currency->is_active))>
                            <label class="form-check-label" for="is_active">{{ __('app.currency_active_help') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('admin.currencies.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                <button class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
