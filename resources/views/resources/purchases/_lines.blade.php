@php
    $lines = old("lines", $linesOld ?? []);
    $lines = is_array($lines) ? $lines : array_map(fn ($l) => (array) $l, $lines);
    if (count($lines) === 0) { $lines = [[]]; }
@endphp
<div class="table-responsive">
    <table class="table align-middle mb-0" id="po-lines">
        <thead>
            <tr>
                <th class="w-40pct">{{ __('app.item') }} <span class="text-danger">*</span></th>
                <th class="w-18pct">{{ __('app.quantity') }} <span class="text-danger">*</span></th>
                <th class="w-22pct">{{ __('app.unit_cost') }} <span class="text-danger">*</span></th>
                <th class="text-end w-15pct">{{ __('app.subtotal') }}</th>
                <th class="text-center w-5pct"></th>
            </tr>
        </thead>
        <tbody id="po-lines-body" data-next-index="{{ count($lines) }}">
            @foreach($lines as $i => $line)
            <tr>
                <td>
                    <select name="lines[{{ $i }}][inventory_item_id]" class="form-select form-select-sm line-item" required>
                        <option value="">—</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" {{ ($line["inventory_item_id"] ?? null) == $item->id ? "selected" : "" }}>{{ $item->name }} ({{ __('app.unit_' . $item->unit) }})</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="number" name="lines[{{ $i }}][quantity]" class="form-control form-control-sm line-qty" min="0.01" step="0.01" value="{{ $line["quantity"] ?? 1 }}" required>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" name="lines[{{ $i }}][unit_cost]" class="form-control line-cost" min="0" step="0.01" value="{{ $line["unit_cost"] ?? '' }}" required>
                        <span class="input-group-text">{{ currency_symbol() }}</span>
                    </div>
                </td>
                <td class="text-end line-subtotal">—</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger" data-po-remove title="{{ __('app.remove') }}"><i class="bi bi-dash-lg"></i></button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if($errors->has("lines"))<div class="text-danger small mt-1">{{ $errors->first("lines") }}</div>@endif
<div class="form-text small">
    <i class="bi bi-info-circle"></i> {{ __('app.po_lines_hint') }}
    <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-po-add><i class="bi bi-plus-lg"></i> {{ __('app.add_line') }}</button>
</div>

<template id="po-line-template">
    <tr>
        <td>
            <select name="__NAME__[inventory_item_id]" class="form-select form-select-sm line-item" required>
                <option value="">—</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }} ({{ __('app.unit_' . $item->unit) }})</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="__NAME__[quantity]" class="form-control form-control-sm line-qty" min="0.01" step="0.01" value="1" required>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" name="__NAME__[unit_cost]" class="form-control line-cost" min="0" step="0.01" required>
                <span class="input-group-text">{{ currency_symbol() }}</span>
            </div>
        </td>
        <td class="text-end line-subtotal">—</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" data-po-remove title="{{ __('app.remove') }}"><i class="bi bi-dash-lg"></i></button>
        </td>
    </tr>
</template>

@push("scripts")
    @vite("resources/js/pages/purchase-lines.js")
@endpush
