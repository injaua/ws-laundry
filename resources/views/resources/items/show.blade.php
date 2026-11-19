@extends("layouts.admin")
@section("title", $item->name)
@section("page_title", $item->name)

@section("content")
@php
    $kindLabel = ["product" => __('app.products'), "material" => __('app.materials'), "part" => __('app.spare_parts')][$item->kind];
@endphp
<div class="page-title">
    <span>
        <span class="badge bg-light text-dark">{{ $kindLabel }}</span>
        @if($item->isLowStock())<span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle"></i> {{ __('app.low_stock') }}</span>@endif
        @if(!$item->is_active)<span class="badge bg-secondary ms-1">{{ __('app.inactive') }}</span>@endif
    </span>
    <a href="{{ route("resources.items.edit", $item) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('app.edit') }}</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">{{ __('app.stock_qty') }}</div>
                <div class="fs-2 fw-bold {{ $item->isLowStock() ? "text-danger" : "" }}">{{ qty($item->stock_qty) }}</div>
                <div class="small text-muted">{{ __('app.unit_' . $item->unit) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">{{ __('app.min_stock_qty') }}</div>
                <div class="fs-2 fw-bold">{{ $item->min_stock_qty > 0 ? qty($item->min_stock_qty) : "—" }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">{{ __('app.unit_cost') }}</div>
                <div class="fs-2 fw-bold">{{ $item->unit_cost > 0 ? num($item->unit_cost) : "—" }}</div>
                <div class="small text-muted">{{ currency_symbol() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small">{{ __('app.inventory_value') }}</div>
                <div class="fs-2 fw-bold">{{ num((float) $item->stock_qty * (float) $item->unit_cost) }}</div>
                <div class="small text-muted">{{ currency_symbol() }}</div>
            </div>
        </div>
    </div>
</div>

@if($item->supplier || $item->type || $item->notes)
<div class="card mb-3">
    <div class="card-body small">
        @if($item->type)<span class="me-3"><strong>{{ __('app.type') }}:</strong> {{ __('app.item_type_' . $item->type) }}</span>@endif
        @if($item->supplier)<span class="me-3"><strong>{{ __('app.supplier') }}:</strong> {{ $item->supplier }}</span>@endif
        @if($item->notes)<span><strong>{{ __('app.notes') }}:</strong> {{ $item->notes }}</span>@endif
    </div>
</div>
@endif

<div class="card mb-3">
    <div class="card-header">{{ __('app.new_movement') }}</div>
    <div class="card-body">
        <form action="{{ route("resources.movements.store", $item) }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label small">{{ __('app.direction') }}</label>
                <select name="direction" id="m-direction" class="form-select" required>
                    <option value="in" {{ old("direction") == "in" ? "selected" : "" }}>{{ __('app.movement_in') }}</option>
                    <option value="out" {{ old("direction") == "out" ? "selected" : "" }}>{{ __('app.movement_out') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">{{ __('app.quantity') }} <span class="text-danger">*</span></label>
                <input type="number" name="quantity" class="form-control" min="0.01" step="0.01" value="{{ old("quantity", 1) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">{{ __('app.unit_cost') }}</label>
                <div class="input-group">
                    <input type="number" name="unit_cost" id="m-unit-cost" data-required-if="direction=in" class="form-control" min="0" step="0.01" value="{{ old("unit_cost", $item->unit_cost > 0 ? (float) $item->unit_cost : "") }}">
                    <span class="input-group-text">{{ currency_symbol() }}</span>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small">{{ __('app.reason') }}</label>
                <select name="reason" id="m-reason" class="form-select">
                    @foreach(\App\Http\Controllers\InventoryMovementController::FORM_REASONS as $rs)
                        <option value="{{ $rs }}" {{ old("reason", "purchase") == $rs ? "selected" : "" }}>{{ __('app.movement_reason_' . $rs) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">{{ __('app.date') }}</label>
                <input type="date" name="moved_at" class="form-control" value="{{ old("moved_at", now()->toDateString()) }}" required>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> {{ __('app.save') }}</button>
            </div>

            <div id="lot-fields" class="w-100" data-show-if="direction=in">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label small">{{ __('app.lot_number') }}</label>
                        <input type="text" name="lot_number" class="form-control @error('lot_number') is-invalid @enderror" maxlength="60" value="{{ old("lot_number") }}">
                        @error('lot_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">{{ __('app.expiry_date') }}</label>
                        <input type="date" name="expires_at" class="form-control" value="{{ old("expires_at") }}">
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted mt-4"><i class="bi bi-info-circle"></i> {{ __('app.lot_hint_in') }}</div>
                    </div>
                </div>
            </div>
            <div id="expired-fields" class="w-100" data-show-if="direction=out">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="allow_expired" value="1" id="allow_expired" {{ old("allow_expired") ? "checked" : "" }}>
                    <label class="form-check-label small" for="allow_expired">{{ __('app.allow_expired_lots') }}</label>
                </div>
            </div>

            <div id="expense-fields" class="w-100" data-show-if="direction=in&reason=purchase">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="register_expense" value="1" id="register_expense" {{ old("register_expense", true) ? "checked" : "" }}>
                    <label class="form-check-label small" for="register_expense">{{ __('app.register_expense') }}</label>
                </div>
                <div class="mt-1">
                    <label class="form-label small">{{ __('app.expense_category') }}</label>
                    <select name="expense_category" class="form-select form-select-sm mw-280px">
                        @foreach($categories as $category)
                            <option value="{{ $category->key }}" {{ old("expense_category", $item->kind == "product" ? "cleaning_products" : "other") == $category->key ? "selected" : "" }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
        @error("quantity")<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        @error("moved_at")<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</div>


<div class="card">
    <div class="card-header">{{ __('app.stock_movements') }}</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.date') }}</th>
                        <th>{{ __('app.direction') }}</th>
                        <th class="text-end">{{ __('app.quantity') }}</th>
                        <th>{{ __('app.reason') }}</th>
                        <th>{{ __('app.lot_number') }}</th>
                        <th class="text-end">{{ __('app.total_value') }}</th>
                        <th>{{ __('app.user') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($item->movements as $movement)
                    <tr>
                        <td class="small">{{ $movement->moved_at->format(date_fmt()) }}</td>
                        <td>
                            @if($movement->direction == "in")
                                <span class="badge bg-success-subtle text-success"><i class="bi bi-arrow-down-left"></i> {{ __('app.movement_in') }}</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger"><i class="bi bi-arrow-up-right"></i> {{ __('app.movement_out') }}</span>
                            @endif
                        </td>
                        <td class="text-end">{{ qty($movement->quantity) }} {{ __('app.unit_' . $item->unit) }}</td>
                        <td class="small">
                            {{ __('app.movement_reason_' . $movement->reason) }}
                            @if($movement->expense_id)
                                <span class="badge bg-info-subtle text-info ms-1" title="{{ __('app.linked_expense') }}"><i class="bi bi-cash-stack"></i></span>
                            @endif
                        </td>
                        <td class="small">
                            @if($movement->inventory_lot_id)
                                {{ $movement->lot->lot_number ?? "—" }}
                                @if($movement->lot?->isExpired())
                                    <span class="badge bg-danger-subtle text-danger ms-1">{{ __('app.expired') }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end small">{{ $movement->direction == "in" && $movement->unit_cost !== null ? num($movement->expenseAmount()) : "—" }}</td>
                        <td class="small">{{ $movement->user->name ?? "—" }}</td>
                        <td class="text-center">
                            @if($movement->source_type === "order")
                                <small class="text-muted"><i class="bi bi-bag-check"></i> {{ __('app.via_order') }}</small>
                            @elseif($movement->source_type === "purchase_order")
                                <small class="text-muted"><i class="bi bi-bag-plus"></i> {{ __('app.via_purchase_order') }}</small>
                            @elseif($movement->reason === "maintenance")
                                <small class="text-muted"><i class="bi bi-tools"></i> {{ __('app.via_maintenance') }}</small>
                            @elseif($movement->inventory_lot_id)
                                <form action="{{ route("resources.movements.destroy", $movement) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_confirm') }}">
                                    @csrf @method("DELETE")
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            @else
                                <a href="{{ route("resources.movements.edit", $movement) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route("resources.movements.destroy", $movement) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_confirm') }}">
                                    @csrf @method("DELETE")
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-4 text-muted">{{ __('app.no_movements') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($item->kind === "part" && $jobParts->isNotEmpty())
<div class="card mt-3">
    <div class="card-header">{{ __('app.job_parts') }}</div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <tbody>
                @foreach($jobParts as $jobPart)
                <tr>
                    <td class="small">{{ $jobPart->created_at->format(date_fmt()) }}</td>
                    <td><a href="{{ route("resources.maintenance.show", $jobPart->maintenance_job_id) }}" class="text-decoration-none">{{ $jobPart->job->title }}</a></td>
                    <td class="small text-muted">{{ $jobPart->job->equipment->name ?? "" }}</td>
                    <td class="text-end">{{ qty($jobPart->quantity) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
