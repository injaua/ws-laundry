{{-- "Request a pickup" form. Shown only when at least one delivery zone is active. Expects: $zones, $stores, $publicStore. --}}
<section class="bg-surface" id="pickup">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-5" data-reveal>
                <div class="section-label">{{ __('app.pickup_section_label') }}</div>
                <h2 class="section-title">{{ __('app.pickup_section_title') }}</h2>
                <p class="section-desc">{{ __('app.pickup_section_desc') }}</p>
                @if($stores->count() > 1)
                    {{-- The request goes to the chosen store: its zones and fees are listed below. --}}
                    <div class="small fw-semibold mb-1">{{ __('app.pickup_choose_store') }}</div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach($stores as $store)
                            <a href="{{ route("home", ["store" => $store->id]) }}#pickup" class="btn btn-sm {{ $publicStore?->id === $store->id ? "btn-primary" : "btn-outline-primary" }}">{{ $store->name }}</a>
                        @endforeach
                    </div>
                @endif
                <ul class="list-unstyled small mt-3">
                    @foreach($zones as $zone)
                        <li class="mb-2"><i class="bi bi-geo-alt text-accent"></i> <strong>{{ $zone->name }}</strong> · {{ $zone->feeLabel() }}
                            @if($zone->description)<div class="text-muted ms-3">{{ $zone->description }}</div>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="col-lg-7" data-reveal>
                <div class="status-card">
                    @if(session("pickup_success"))
                        <div class="os-empty py-4">
                            <i class="bi bi-check2-circle"></i>
                            <h3>{{ __('app.pickup_request_received') }}</h3>
                            <p>{{ __('app.pickup_request_received_desc') }}</p>
                        </div>
                    @else
                    @php($errs = $errors->getBag("pickup"))
                    <form action="{{ route("pickup-requests.store") }}" method="POST" class="row g-3">
                        @csrf
                        <input type="hidden" name="store" value="{{ $publicStore?->id }}">
                        {{-- Honeypot --}}
                        <div class="offscreen" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="pk_name">{{ __('app.name') }}</label>
                            <input type="text" name="contact_name" id="pk_name" maxlength="100" required class="form-control {{ $errs->has("contact_name") ? "is-invalid" : "" }}" value="{{ old("contact_name") }}">
                            @if($errs->has("contact_name"))<div class="invalid-feedback">{{ $errs->first("contact_name") }}</div>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="pk_phone">{{ __('app.phone') }} / WhatsApp</label>
                            <input type="tel" name="contact_phone" id="pk_phone" maxlength="30" required class="form-control {{ $errs->has("contact_phone") ? "is-invalid" : "" }}" value="{{ old("contact_phone") }}" placeholder="84 123 4567">
                            @if($errs->has("contact_phone"))<div class="invalid-feedback">{{ $errs->first("contact_phone") }}</div>@endif
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold" for="pk_address">{{ __('app.address') }}</label>
                            <input type="text" name="address" id="pk_address" maxlength="255" required class="form-control {{ $errs->has("address") ? "is-invalid" : "" }}" value="{{ old("address") }}">
                            @if($errs->has("address"))<div class="invalid-feedback">{{ $errs->first("address") }}</div>@endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold" for="pk_zone">{{ __('app.delivery_zone') }}</label>
                            <select name="delivery_zone_id" id="pk_zone" required class="form-select {{ $errs->has("delivery_zone_id") ? "is-invalid" : "" }}">
                                <option value="">—</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}" @selected((string) old("delivery_zone_id") === (string) $zone->id)>{{ $zone->name }}</option>
                                @endforeach
                            </select>
                            @if($errs->has("delivery_zone_id"))<div class="invalid-feedback">{{ $errs->first("delivery_zone_id") }}</div>@endif
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold" for="pk_ref">{{ __('app.address_reference') }}</label>
                            <input type="text" name="address_reference" id="pk_ref" maxlength="255" class="form-control" value="{{ old("address_reference") }}" placeholder="{{ __('app.address_reference_placeholder') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="pk_date">{{ __('app.preferred_date') }}</label>
                            <input type="date" name="scheduled_date" id="pk_date" required min="{{ today()->toDateString() }}" max="{{ today()->addDays(30)->toDateString() }}" class="form-control {{ $errs->has("scheduled_date") ? "is-invalid" : "" }}" value="{{ old("scheduled_date", today()->addDay()->toDateString()) }}">
                            @if($errs->has("scheduled_date"))<div class="invalid-feedback">{{ $errs->first("scheduled_date") }}</div>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold" for="pk_window">{{ __('app.time_window') }}</label>
                            <select name="time_window" id="pk_window" class="form-select">
                                @foreach(\App\Models\CourierJob::WINDOWS as $w)
                                    <option value="{{ $w }}" @selected(old("time_window", "any") === $w)>{{ __('app.window_' . $w) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold" for="pk_notes">{{ __('app.notes') }}</label>
                            <textarea name="notes" id="pk_notes" rows="2" maxlength="500" class="form-control" placeholder="{{ __('app.pickup_notes_public_placeholder') }}">{{ old("notes") }}</textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn-pill btn-solid"><i class="bi bi-truck"></i> {{ __('app.request_pickup') }}</button>
                            <div class="small text-muted mt-2">{{ __('app.pickup_request_hint') }}</div>
                        </div>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
