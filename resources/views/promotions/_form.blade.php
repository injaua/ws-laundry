@php
    $selectedServices = collect(old("services", $promotion->items?->pluck("service_id")->filter()->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedPackages = collect(old("packages", $promotion->items?->pluck("package_id")->filter()->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $weekdays = collect(old("weekdays", $promotion->weekdays ?? []))->map(fn ($d) => (int) $d)->all();
    $scope = old("applies_to", $promotion->applies_to ?? "selected");
    $type = old("discount_type", $promotion->discount_type ?? "percent");
@endphp
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="name">{{ __('app.name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" maxlength="100" required class="form-control @error("name") is-invalid @enderror" value="{{ old("name", $promotion->name) }}" placeholder="{{ __('app.promotion_name_placeholder') }}">
        @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old("is_active", $promotion->is_active))>
            <label class="form-check-label" for="is_active">{{ __('app.active') }}</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">{{ __('app.description') }}</label>
        <textarea name="description" id="description" rows="2" maxlength="1000" class="form-control">{{ old("description", $promotion->description) }}</textarea>
    </div>

    <div class="col-md-6">
        <label class="form-label d-block">{{ __('app.discount') }} <span class="text-danger">*</span></label>
        <div class="input-group">
            <select name="discount_type" id="discount_type" class="form-select mw-170px">
                @foreach(\App\Models\Promotion::TYPES as $t)
                    <option value="{{ $t }}" @selected($type === $t)>{{ __('app.discount_type_' . $t) }}</option>
                @endforeach
            </select>
            <input type="number" name="value" id="value" min="0.01" step="0.01" required class="form-control @error("value") is-invalid @enderror" value="{{ old("value", $promotion->value !== null ? (float) $promotion->value : "") }}">
            <span class="input-group-text" id="valueUnit">{{ $type === "percent" ? "%" : currency_symbol() }}</span>
        </div>
        @error("value") <div class="text-danger small">{{ $message }}</div> @enderror
        <div class="form-text {{ $type === "fixed" ? "" : "d-none" }}" id="fixedHint" data-show-if="discount_type=fixed">{{ __('app.fixed_discount_hint') }}</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="starts_on">{{ __('app.period_start') }} <span class="text-danger">*</span></label>
        <input type="date" name="starts_on" id="starts_on" required class="form-control @error("starts_on") is-invalid @enderror" value="{{ old("starts_on", $promotion->starts_on?->toDateString()) }}">
        @error("starts_on") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="ends_on">{{ __('app.period_end') }}</label>
        <input type="date" name="ends_on" id="ends_on" class="form-control @error("ends_on") is-invalid @enderror" value="{{ old("ends_on", $promotion->ends_on?->toDateString()) }}">
        @error("ends_on") <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">{{ __('app.no_end_date_hint') }}</div>
    </div>

    <div class="col-12">
        <label class="form-label d-block">{{ __('app.promotion_weekdays') }}</label>
        @foreach([1, 2, 3, 4, 5, 6, 0] as $d)
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="weekdays[]" id="wd{{ $d }}" value="{{ $d }}" @checked(in_array($d, $weekdays, true))>
                <label class="form-check-label" for="wd{{ $d }}">{{ __('app.weekday_short_' . $d) }}</label>
            </div>
        @endforeach
        <div class="form-text">{{ __('app.promotion_weekdays_hint') }}</div>
    </div>

    <div class="col-12">
        <label class="form-label d-block">{{ __('app.applies_to') }}</label>
        @foreach(\App\Models\Promotion::SCOPES as $s)
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="applies_to" id="scope_{{ $s }}" value="{{ $s }}" @checked($scope === $s)>
                <label class="form-check-label" for="scope_{{ $s }}">{{ __('app.promotion_scope_' . $s) }}</label>
            </div>
        @endforeach
    </div>
    <div class="col-12 {{ $scope === "selected" ? "" : "d-none" }}" id="itemsWrap" data-show-if="applies_to=selected">
        @error("services") <div class="text-danger small mb-2">{{ $message }}</div> @enderror
        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded p-2 mh-260px overflow-auto">
                    <div class="small fw-semibold mb-1">{{ __('app.services') }}</div>
                    @forelse($services as $service)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="services[]" id="svc{{ $service->id }}" value="{{ $service->id }}" @checked(in_array($service->id, $selectedServices, true))>
                            <label class="form-check-label small" for="svc{{ $service->id }}">
                                {{ $service->name }}
                                <span class="text-muted">· {{ money((float) $service->price) }}/{{ $service->type === "by_weight" ? "kg" : __('app.pieces') }}</span>
                            </label>
                        </div>
                    @empty
                        <div class="small text-muted">-</div>
                    @endforelse
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-2 mh-260px overflow-auto">
                    <div class="small fw-semibold mb-1">{{ __('app.packages') }}</div>
                    @forelse($packages as $package)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="packages[]" id="pkg{{ $package->id }}" value="{{ $package->id }}" @checked(in_array($package->id, $selectedPackages, true))>
                            <label class="form-check-label small" for="pkg{{ $package->id }}">
                                {{ $package->name }}
                                <span class="text-muted">· {{ (float) $package->weight_kg }} kg · {{ money((float) $package->price) }}</span>
                            </label>
                        </div>
                    @empty
                        <div class="small text-muted">-</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@push("scripts")
    @vite("resources/js/pages/promotion-form.js")
@endpush
