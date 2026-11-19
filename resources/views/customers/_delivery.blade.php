{{-- Where couriers find the customer. Expects: $customer (nullable). --}}
{{-- $deliveryZones: App\View\Composers\DeliveryFieldsComposer --}}
<div class="row g-3 mb-3">
    <div class="col-md-5">
        <label class="form-label" for="delivery_zone_id">{{ __('app.delivery_zone') }}</label>
        <select name="delivery_zone_id" id="delivery_zone_id" class="form-select @error("delivery_zone_id") is-invalid @enderror">
            <option value="">—</option>
            @foreach($deliveryZones as $zone)
                <option value="{{ $zone->id }}" @selected((string) old("delivery_zone_id", $customer?->delivery_zone_id) === (string) $zone->id)>{{ $zone->name }}</option>
            @endforeach
        </select>
        @error("delivery_zone_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-7">
        <label class="form-label" for="address_reference">{{ __('app.address_reference') }}</label>
        <input type="text" name="address_reference" id="address_reference" maxlength="255" class="form-control @error("address_reference") is-invalid @enderror"
            value="{{ old("address_reference", $customer?->address_reference) }}" placeholder="{{ __('app.address_reference_placeholder') }}">
        @error("address_reference") <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
