@extends("layouts.admin")
@section("title", __('app.business_settings'))
@section("page_title", __('app.business_settings'))

@section("content")
<form method="POST">
    @csrf
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-percent me-1"></i> {{ __('app.fiscal_settings') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.iva_rate') }}</label>
                        <div class="input-group" style="max-width:220px">
                            <input type="number" name="iva_rate" step="0.5" min="0" max="100" class="form-control @error("iva_rate") is-invalid @enderror"
                                value="{{ $settings["iva_rate"] ?? 16 }}">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">{{ __('app.iva_settings_hint') }}</div>
                        @error("iva_rate") <div class="text-danger small">{{ $message }}</div> @enderror
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
