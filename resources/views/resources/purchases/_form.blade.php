@php($purchase = $purchase ?? null)
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label class="form-label">{{ __('app.supplier') }} <span class="text-danger">*</span></label>
        <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
            <option value="">—</option>
            @foreach($suppliers as $supplierOption)
                <option value="{{ $supplierOption->id }}" {{ old("supplier_id", $purchase?->supplier_id) == $supplierOption->id ? "selected" : "" }}>{{ $supplierOption->name }}</option>
            @endforeach
        </select>
        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.order_date') }} <span class="text-danger">*</span></label>
        <input type="date" name="order_date" class="form-control @error('order_date') is-invalid @enderror" value="{{ old('order_date', $purchase?->order_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
        @error('order_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.expected_date') }}</label>
        <input type="date" name="expected_date" class="form-control @error('expected_date') is-invalid @enderror" value="{{ old('expected_date', $purchase?->expected_date?->format('Y-m-d')) }}">
        @error('expected_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label">{{ __('app.po_number') }}</label>
        <input type="text" class="form-control" value="{{ $purchase?->po_number ?? __('app.po_auto_number') }}" disabled>
    </div>
    <div class="col-12">
        <label class="form-label">{{ __('app.notes') }}</label>
        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $purchase?->notes) }}</textarea>
    </div>
</div>

<div class="card">
    <div class="card-header">{{ __('app.lines') }}</div>
    <div class="card-body p-0">
        @include("resources.purchases._lines", ["linesOld" => ($purchase?->items ?? collect())->map(fn ($l) => [
            "inventory_item_id" => $l->inventory_item_id,
            "quantity" => (float) $l->quantity,
            "unit_cost" => (int) $l->unit_cost,
        ])->values()->toArray()])
    </div>
</div>
