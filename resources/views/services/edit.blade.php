@extends("layouts.admin")
@section("title", __('app.edit_service'))
@section("page_title", __('app.edit_service'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-gear me-1"></i> {{ __('app.edit_service') }}</div>
            <div class="card-body">
                <form action="{{ route("services.update", $service) }}" method="POST" enctype="multipart/form-data">
                    @csrf @method("PUT")
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.service_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error("name") is-invalid @enderror" value="{{ old("name", $service->name) }}" required>
                        @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.service_type') }} <span class="text-danger">*</span></label>
                        <select name="type" class="form-select @error("type") is-invalid @enderror" required>
                            <option value="by_weight" {{ old("type", $service->type) == "by_weight" ? "selected" : "" }}>{{ __('app.per_kg') }}</option>
                            <option value="by_piece" {{ old("type", $service->type) == "by_piece" ? "selected" : "" }}>{{ __('app.per_piece') }}</option>
                        </select>
                        @error("type") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.price') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="price" class="form-control @error("price") is-invalid @enderror" value="{{ old("price", $service->price) }}" required min="0" max="10000">
                            <span class="input-group-text">Mts</span>
                            @error("price") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.estimated_work') }}</label>
                        <div class="input-group">
                            <input type="number" name="estimated_days" class="form-control @error("estimated_days") is-invalid @enderror" value="{{ old("estimated_days", $service->estimated_days) }}" min="1">
                            <span class="input-group-text">{{ __('app.days') }}</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.service_photo') }}</label>
                        @if($service->photo)
                            <div class="mb-2">
                                <img id="photoPreview" src="{{ Storage::url($service->photo) }}" alt="{{ $service->name }}" class="rounded border" style="max-height:160px">
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="remove_photo" value="1" class="form-check-input" id="removePhoto">
                                <label class="form-check-label" for="removePhoto">{{ __('app.remove_photo') }}</label>
                            </div>
                        @else
                            <div class="mb-2 d-none" id="photoPreviewWrap">
                                <img id="photoPreview" class="rounded border" style="max-height:160px" alt="">
                            </div>
                        @endif
                        <input type="file" name="photo" class="form-control @error("photo") is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">{{ __('app.photo_optional_hint') }}</div>
                        @error("photo") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.update') }}</button>
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
            var img = document.getElementById("photoPreview");
            img.src = URL.createObjectURL(f);
            img.closest("div").classList.remove("d-none");
            var rp = document.getElementById("removePhoto");
            if (rp) rp.checked = false;
        }
    });
</script>
@endpush
