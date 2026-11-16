@extends("layouts.admin")
@section("title", __('app.new_service'))
@section("page_title", __('app.new_service'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle me-1"></i> {{ __('app.new_service') }}</div>
            <div class="card-body">
                <form action="{{ route("services.store") }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.service_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error("name") is-invalid @enderror" value="{{ old("name") }}" required>
                        @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.service_type') }} <span class="text-danger">*</span></label>
                        <select name="type" class="form-select @error("type") is-invalid @enderror" required>
                            <option value="">{{ __('app.select_type') }}</option>
                            <option value="by_weight" {{ old("type") == "by_weight" ? "selected" : "" }}>{{ __('app.per_kg') }}</option>
                            <option value="by_piece" {{ old("type") == "by_piece" ? "selected" : "" }}>{{ __('app.per_piece') }}</option>
                        </select>
                        @error("type") <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                        <label class="form-label">{{ __('app.estimated_work') }}</label>
                        <div class="input-group">
                            <input type="number" name="estimated_days" class="form-control @error("estimated_days") is-invalid @enderror" value="{{ old("estimated_days", 2) }}" min="1">
                            <span class="input-group-text">{{ __('app.days') }}</span>
                            @error("estimated_days") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.service_photo') }}</label>
                        <input type="file" name="photo" class="form-control @error("photo") is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">{{ __('app.photo_optional_hint') }}</div>
                        @error("photo") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3 d-none" id="photoPreviewWrap">
                        <img id="photoPreview" class="rounded border" style="max-height:160px" alt="">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
                        <a href="{{ route("services.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push("scripts")
<script>
    document.querySelector("input[name=photo]").addEventListener("change", function() {
        var f = this.files[0];
        if (f) {
            document.getElementById("photoPreview").src = URL.createObjectURL(f);
            document.getElementById("photoPreviewWrap").classList.remove("d-none");
        }
    });
</script>
@endpush
