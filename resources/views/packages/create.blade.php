@extends("layouts.admin")
@section("title", __('app.new_package'))
@section("page_title", __('app.new_package'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle me-1"></i> {{ __('app.new_package') }}</div>
            <div class="card-body">
                <form action="{{ route("packages.store") }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.package_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error("name") is-invalid @enderror" value="{{ old("name") }}" required maxlength="100">
                        @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.description') }}</label>
                        <textarea name="description" class="form-control @error("description") is-invalid @enderror" rows="3">{{ old("description") }}</textarea>
                        @error("description") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.package_weight') }} <span class="text-danger">*</span></label>
                        <input type="number" name="weight_kg" class="form-control @error("weight_kg") is-invalid @enderror" value="{{ old("weight_kg") }}" required min="0.1" step="0.5">
                        @error("weight_kg") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.price') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="price" class="form-control @error("price") is-invalid @enderror" value="{{ old("price") }}" required min="0" max="10000">
                            <span class="input-group-text">Mts</span>
                            @error("price") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old("is_active", true) ? "checked" : "" }}>
                            <label class="form-check-label" for="is_active">{{ __('app.active') }}</label>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
                        <a href="{{ route("packages.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
