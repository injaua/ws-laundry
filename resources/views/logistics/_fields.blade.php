{{-- Address, schedule and courier fields of a trip. Expects: $zones, $couriers; optional $job (editing) and $defaults. --}}
@php
    $job = $job ?? null;
    $defaults = $defaults ?? [];
    $val = fn ($field, $fallback = null) => old($field, $job?->{$field} ?? ($defaults[$field] ?? $fallback));
@endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="contact_name">{{ __('app.contact_name') }}</label>
        <input type="text" name="contact_name" id="contact_name" maxlength="100" class="form-control @error("contact_name") is-invalid @enderror" value="{{ $val("contact_name") }}" @if($job) required @endif>
        @error("contact_name") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="contact_phone">{{ __('app.phone') }}</label>
        <input type="text" name="contact_phone" id="contact_phone" maxlength="30" class="form-control @error("contact_phone") is-invalid @enderror" value="{{ $val("contact_phone") }}" @if($job) required @endif>
        @error("contact_phone") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-8">
        <label class="form-label" for="address">{{ __('app.address') }} <span class="text-danger">*</span></label>
        <input type="text" name="address" id="address" maxlength="255" required class="form-control @error("address") is-invalid @enderror" value="{{ $val("address") }}">
        @error("address") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="delivery_zone_id">{{ __('app.delivery_zone') }}</label>
        <select name="delivery_zone_id" id="delivery_zone_id" class="form-select @error("delivery_zone_id") is-invalid @enderror">
            <option value="">—</option>
            @foreach($zones as $zone)
                <option value="{{ $zone->id }}" data-fee="{{ (float) $zone->fee }}" @selected((string) $val("delivery_zone_id") === (string) $zone->id)>{{ $zone->name }} · {{ $zone->feeLabel() }}</option>
            @endforeach
        </select>
        @error("delivery_zone_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="address_reference">{{ __('app.address_reference') }}</label>
        <input type="text" name="address_reference" id="address_reference" maxlength="255" class="form-control" value="{{ $val("address_reference") }}" placeholder="{{ __('app.address_reference_placeholder') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="scheduled_date">{{ __('app.date') }} <span class="text-danger">*</span></label>
        <input type="date" name="scheduled_date" id="scheduled_date" required class="form-control @error("scheduled_date") is-invalid @enderror" value="{{ old("scheduled_date", $job?->scheduled_date?->toDateString() ?? ($defaults["scheduled_date"] ?? today()->toDateString())) }}">
        @error("scheduled_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="time_window">{{ __('app.time_window') }}</label>
        <select name="time_window" id="time_window" class="form-select">
            @foreach(\App\Models\CourierJob::WINDOWS as $w)
                <option value="{{ $w }}" @selected($val("time_window", "any") === $w)>{{ __('app.window_' . $w) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="courier_id">{{ __('app.courier') }}</label>
        <select name="courier_id" id="courier_id" class="form-select @error("courier_id") is-invalid @enderror">
            <option value="">{{ __('app.unassigned') }}</option>
            @foreach($couriers as $c)
                <option value="{{ $c->id }}" @selected((string) $val("courier_id") === (string) $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        @error("courier_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
        @if($couriers->isEmpty())<div class="form-text">{{ __('app.no_couriers_hint') }}</div>@endif
    </div>
    @if(auth()->user()->hasRole(["admin", "owner"]))
    <div class="col-md-4">
        <label class="form-label" for="fee">{{ __('app.delivery_fee') }} ({{ currency_symbol() }})</label>
        <input type="number" name="fee" id="fee" min="0" step="0.01" class="form-control @error("fee") is-invalid @enderror" value="{{ old("fee", $job ? (float) $job->fee : "") }}" placeholder="{{ __('app.zone_fee_placeholder') }}">
        @error("fee") <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">{{ __('app.fee_override_hint') }}</div>
    </div>
    @endif
    <div class="col-md-{{ auth()->user()->hasRole(["admin", "owner"]) ? 8 : 12 }}">
        <label class="form-label" for="notes">{{ __('app.notes') }}</label>
        <input type="text" name="notes" id="notes" maxlength="1000" class="form-control" value="{{ $val("notes") }}">
    </div>
</div>
