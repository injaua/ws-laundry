@php($equipment = $equipment ?? null)
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $equipment?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.equipment_code') }}</label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $equipment?->code) }}">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.equipment_type') }} <span class="text-danger">*</span></label>
        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
            @foreach(\App\Models\Equipment::TYPES as $tp)
                <option value="{{ $tp }}" {{ old('type', $equipment?->type) == $tp ? "selected" : "" }}>{{ __('app.eq_type_' . $tp) }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.brand') }}</label>
        <input type="text" name="brand" class="form-control" value="{{ old('brand', $equipment?->brand) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.model') }}</label>
        <input type="text" name="model" class="form-control" value="{{ old('model', $equipment?->model) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.serial_number') }}</label>
        <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number', $equipment?->serial_number) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.purchase_date') }}</label>
        <input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', $equipment?->purchase_date?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.purchase_price') }}</label>
        <div class="input-group">
            <input type="number" name="purchase_price" class="form-control" min="0" step="1" value="{{ old('purchase_price', $equipment?->purchase_price) }}">
            <span class="input-group-text">{{ currency_symbol() }}</span>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.warranty_until') }}</label>
        <input type="date" name="warranty_until" class="form-control" value="{{ old('warranty_until', $equipment?->warranty_until?->format('Y-m-d')) }}">
    </div>

    <div class="col-md-5">
        <label class="form-label">{{ __('app.supplier') }}</label>
        <input type="text" name="supplier" class="form-control" value="{{ old('supplier', $equipment?->supplier) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.location') }}</label>
        <input type="text" name="location" class="form-control" value="{{ old('location', $equipment?->location) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.capacity_kg') }}</label>
        <input type="number" name="capacity_kg" min="0" step="0.5" class="form-control @error('capacity_kg') is-invalid @enderror" value="{{ old('capacity_kg', $equipment?->capacity_kg) }}">
        <div class="form-text">{{ __('app.capacity_kg_hint') }}</div>
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.service_interval_cycles') }}</label>
        <input type="number" name="service_interval_cycles" min="1" class="form-control @error('service_interval_cycles') is-invalid @enderror" value="{{ old('service_interval_cycles', $equipment?->service_interval_cycles) }}">
        <div class="form-text">{{ __('app.service_interval_hint') }}</div>
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.status') }} <span class="text-danger">*</span></label>
        <select name="status" class="form-select" required>
            @foreach(\App\Models\Equipment::STATUSES as $st)
                <option value="{{ $st }}" {{ old('status', $equipment?->status ?? 'operational') == $st ? "selected" : "" }}>{{ __('app.status_' . $st) }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.photo') }}</label>
        @if($equipment?->photo)
            <div class="mb-2">
                <img src="{{ asset('storage/' . $equipment->photo) }}" alt="{{ $equipment->name }}" class="rounded border mh-100px">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                    <label class="form-check-label small" for="remove_photo">{{ __('app.remove_photo') }}</label>
                </div>
            </div>
        @endif
        <input type="file" name="photo" class="form-control @error('photo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
        @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">{{ __('app.notes') }}</label>
        <textarea name="notes" rows="3" class="form-control">{{ old('notes', $equipment?->notes) }}</textarea>
    </div>
</div>
