@extends("layouts.admin")
@section("title", __('app.upload_photo'))
@section("page_title", __('app.upload_photo'))
@section("page_subtitle", "Add photos to the landing page gallery")

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route("admin.galleries.store") }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.photo') }}</label>
                        <input type="file" name="photo" class="form-control @error("photo") is-invalid @enderror"
                            accept="image/jpeg,image/png,image/webp" required>
                        @error("photo") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Caption</label>
                        <input type="text" name="caption" class="form-control" value="{{ old("caption") }}">
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('app.sort_order') }}</label>
                            <input type="number" name="sort_order" class="form-control" value="{{ old("sort_order",0) }}" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('app.active') }}</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_active" class="form-check-input" value="1" checked id="active">
                                <label class="form-check-label" for="active">{{ __('app.show_on_site') }}</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> {{ __('app.upload') }}</button>
                        <a href="{{ route("admin.galleries.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
