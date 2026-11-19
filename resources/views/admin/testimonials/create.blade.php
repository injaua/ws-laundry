@extends("layouts.admin")
@section("title", "Add Testimonialal")
@section("page_title", "Add Testimonialal")
@section("page_subtitle", "Add a new customer testimonial")

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="{{ route("admin.testimonials.store") }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Customer Name</label>
                        <input type="text" name="customer_name" class="form-control @error("customer_name") is-invalid @enderror"
                            value="{{ old("customer_name") }}" required>
                        @error("customer_name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Testimonial</label>
                        <textarea name="content" class="form-control @error("content") is-invalid @enderror" rows="4" required>{{ old("content") }}</textarea>
                        @error("content") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('app.rating') }}</label>
                            <select name="rating" class="form-select">
                                @for($i=5;$i>=1;$i--)
                                    <option value="{{ $i }}" {{ old("rating",5)==$i?"selected":"" }}>{{ $i }} ★</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('app.sort_order') }}</label>
                            <input type="number" name="sort_order" class="form-control" value="{{ old("sort_order",0) }}" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('app.active') }}</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_active" class="form-check-input" value="1" checked id="active">
                                <label class="form-check-label" for="active">{{ __('app.show_on_site') }}</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Photo (optional)</label>
                        <input type="file" name="photo" class="form-control @error("photo") is-invalid @enderror"
                            accept="image/jpeg,image/png,image/webp">
                        @error("photo") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save</button>
                        <a href="{{ route("admin.testimonials.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
