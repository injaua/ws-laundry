@php($item = $item ?? null)
@php($types = \App\Models\InventoryItem::TYPES[$kind])
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $item?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.type') }}</label>
        <select name="type" class="form-select">
            <option value="">—</option>
            @foreach($types as $tp)
                <option value="{{ $tp }}" {{ old('type', $item?->type) == $tp ? "selected" : "" }}>{{ __('app.item_type_' . $tp) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.unit') }} <span class="text-danger">*</span></label>
        <select name="unit" class="form-select" required>
            @foreach(\App\Models\InventoryItem::UNITS as $un)
                <option value="{{ $un }}" {{ old('unit', $item?->unit ?? 'un') == $un ? "selected" : "" }}>{{ __('app.unit_' . $un) }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.min_stock_qty') }}</label>
        <input type="number" name="min_stock_qty" class="form-control" min="0" step="0.01" value="{{ old('min_stock_qty', $item?->min_stock_qty ?? 0) }}">
        <div class="form-text">{{ __('app.min_stock_hint') }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.supplier') }}</label>
        <input type="text" name="supplier" class="form-control" value="{{ old('supplier', $item?->supplier) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label d-block">{{ __('app.status') }}</label>
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" {{ old('is_active', $item?->is_active ?? true) ? "checked" : "" }}>
            <label class="form-check-label small" for="is_active">{{ __('app.active') }}</label>
        </div>
    </div>
    <div class="col-md-12">
        <label class="form-label">{{ __('app.notes') }}</label>
        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $item?->notes) }}</textarea>
    </div>
</div>
