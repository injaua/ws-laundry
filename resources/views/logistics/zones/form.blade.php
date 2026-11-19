@extends("layouts.admin")
@section("title", $zone->exists ? __('app.edit_zone') : __('app.new_zone'))
@section("page_title", $zone->exists ? __('app.edit_zone') : __('app.new_zone'))
@section("page_subtitle", __('app.zones_of_store', ["store" => \App\Support\CurrentStore::store()?->name]))

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form action="{{ $zone->exists ? route("delivery-zones.update", $zone) : route("delivery-zones.store") }}" method="POST">
                    @csrf
                    @if($zone->exists) @method("PUT") @endif
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">{{ __('app.name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" maxlength="100" required class="form-control @error("name") is-invalid @enderror" value="{{ old("name", $zone->name) }}" placeholder="{{ __('app.zone_name_placeholder') }}">
                            @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sort_order">{{ __('app.sort_order') }}</label>
                            <input type="number" name="sort_order" id="sort_order" min="0" class="form-control" value="{{ old("sort_order", $zone->sort_order ?? 0) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">{{ __('app.zone_areas') }}</label>
                            <textarea name="description" id="description" rows="2" maxlength="1000" class="form-control" placeholder="{{ __('app.zone_areas_placeholder') }}">{{ old("description", $zone->description) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="fee">{{ __('app.fee_per_trip') }} ({{ currency_symbol() }}) <span class="text-danger">*</span></label>
                            <input type="number" name="fee" id="fee" min="0" step="0.01" required class="form-control @error("fee") is-invalid @enderror" value="{{ old("fee", (float) $zone->fee) }}">
                            @error("fee") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">{{ __('app.fee_per_trip_hint') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="free_from">{{ __('app.free_from') }} ({{ currency_symbol() }})</label>
                            <input type="number" name="free_from" id="free_from" min="0" step="0.01" class="form-control @error("free_from") is-invalid @enderror" value="{{ old("free_from", $zone->free_from !== null ? (float) $zone->free_from : "") }}">
                            @error("free_from") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">{{ __('app.free_from_hint') }}</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old("is_active", $zone->is_active))>
                                <label class="form-check-label" for="is_active">{{ __('app.zone_active_hint') }}</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
                        <a href="{{ route("delivery-zones.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
