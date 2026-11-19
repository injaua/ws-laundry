{{-- One item line of the order form, cloned by resources/js/pages/order-form.js (__IDX__ = line number). --}}
<template id="order-item-template">
    <div class="item-card">
        <div class="item-head">
            <span class="item-index"><span class="pill" data-index-label></span> {{ __('app.item') }}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove aria-label="{{ __('app.delete') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="row g-3">
            <div class="col-lg-5">
                <label class="field-label">{{ __('app.service') }} / {{ __('app.packages') }} <span class="text-danger">*</span></label>
                <select class="form-select item-select" required></select>
                <input type="hidden" name="items[__IDX__][service_id]" class="service-id-input" value="">
                <input type="hidden" name="items[__IDX__][package_id]" class="package-id-input" value="">
            </div>
            <div class="col-lg-3">
                <div class="measure-weight d-none">
                    <label class="field-label">{{ __('app.weight') }} (kg) <span class="text-danger">*</span></label>
                    <input type="number" name="items[__IDX__][weight]" class="form-control weight-input" step="0.5" min="0.5" placeholder="0.5" value="">
                </div>
                <div class="measure-piece d-none">
                    <label class="field-label">{{ __('app.quantity') }} ({{ __('app.pieces') }}) <span class="text-danger">*</span></label>
                    <input type="number" name="items[__IDX__][amount]" class="form-control amount-input" min="1" value="1">
                </div>
                <div class="measure-package d-none">
                    <label class="field-label">{{ __('app.quantity') }} ({{ __('app.by_package') }}) <span class="text-danger">*</span></label>
                    <input type="number" name="items[__IDX__][amount]" class="form-control amount-input" min="1" value="1">
                </div>
                <div class="measure-placeholder text-muted small">{{ __('app.pick_service_first') }}</div>
            </div>
            <div class="col-lg-4">
                <label class="field-label">{{ __('app.price') }}</label>
                <div class="input-group">
                    <input type="number" name="items[__IDX__][price]" class="form-control price-input" readonly tabindex="-1" value="">
                    <span class="input-group-text">{{ currency_symbol() }}</span>
                </div>
                <input type="hidden" name="items[__IDX__][subtotal]" class="subtotal-input" value="0">
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="unit-suffix text-muted small"></span>
                    <span class="item-subtotal">{{ money(0) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
