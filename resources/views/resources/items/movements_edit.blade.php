@extends("layouts.admin")
@section("title", __('app.edit_movement'))
@section("page_title", __('app.edit_movement'))

@section("content")
@php($item = $movement->item)
<div class="card">
    <form action="{{ route("resources.movements.update", $movement) }}" method="POST" class="card-body">
        @csrf @method("PUT")
        <div class="mb-3 small text-muted">
            <i class="bi bi-box-seam"></i> <a href="{{ route("resources.items.show", $item) }}" class="text-decoration-none">{{ $item->name }}</a>
        </div>
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">{{ __('app.direction') }}</label>
                <select name="direction" id="m-direction" class="form-select" required>
                    <option value="in" {{ old("direction", $movement->direction) == "in" ? "selected" : "" }}>{{ __('app.movement_in') }}</option>
                    <option value="out" {{ old("direction", $movement->direction) == "out" ? "selected" : "" }}>{{ __('app.movement_out') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('app.quantity') }}</label>
                <input type="number" name="quantity" class="form-control" min="0.01" step="0.01" value="{{ old("quantity", $movement->quantity) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ __('app.unit_cost') }}</label>
                <div class="input-group">
                    <input type="number" name="unit_cost" id="m-unit-cost" class="form-control" min="0" step="0.01" value="{{ old("unit_cost", $movement->unit_cost) }}">
                    <span class="input-group-text">{{ currency_symbol() }}</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('app.reason') }}</label>
                <select name="reason" id="m-reason" class="form-select">
                    @foreach(\App\Http\Controllers\InventoryMovementController::FORM_REASONS as $rs)
                        <option value="{{ $rs }}" {{ old("reason", $movement->reason) == $rs ? "selected" : "" }}>{{ __('app.movement_reason_' . $rs) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('app.date') }}</label>
                <input type="date" name="moved_at" class="form-control" value="{{ old("moved_at", $movement->moved_at->format("Y-m-d")) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('app.notes') }}</label>
                <input type="text" name="notes" class="form-control" value="{{ old("notes", $movement->notes) }}">
            </div>
        </div>

        <div id="expense-fields" class="mt-3" data-show-if="direction=in&reason=purchase">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="register_expense" value="1" id="register_expense" {{ old("register_expense", (bool) $movement->expense_id) ? "checked" : "" }}>
                <label class="form-check-label small" for="register_expense">{{ __('app.register_expense') }}</label>
            </div>
            <div class="mt-1">
                <label class="form-label small">{{ __('app.expense_category') }}</label>
                <select name="expense_category" class="form-select form-select-sm mw-280px">
                    @foreach($categories as $category)
                        <option value="{{ $category->key }}" {{ old("expense_category", $movement->expense->category ?? ($item->kind == "product" ? "cleaning_products" : "other")) == $category->key ? "selected" : "" }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
            <a href="{{ route("resources.items.show", $item) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>

@endsection
