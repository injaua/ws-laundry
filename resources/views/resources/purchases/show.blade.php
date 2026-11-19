@extends("layouts.admin")
@section("title", $purchase->po_number)
@section("page_title", __('app.purchase_orders') . ": " . $purchase->po_number)

@section("content")
@php
    $pendingLines = $purchase->items->filter(fn ($l) => $l->pendingQty() > 0);
@endphp
<div class="page-title">
    <span>
        <span class="badge badge-status bg-{{ $purchase->status_color }}">{{ $purchase->status_label }}</span>
    </span>
    <div>
        <a href="{{ route("resources.purchases.document", $purchase) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> {{ __('app.purchase_order') }}</a>
        @if(auth()->user()->isManager())
        @if($purchase->status === "draft")
            <form action="{{ route("resources.purchases.order", $purchase) }}" method="POST" class="d-inline">
                @csrf @method("PATCH")
                <button class="btn btn-success"><i class="bi bi-send"></i> {{ __('app.po_mark_ordered') }}</button>
            </form>
            <a href="{{ route("resources.purchases.edit", $purchase) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('app.edit') }}</a>
        @endif
        @if(in_array($purchase->status, ["draft", "ordered"], true))
            <form action="{{ route("resources.purchases.cancel", $purchase) }}" method="POST" class="d-inline" data-confirm="{{ __('app.po_cancel_confirm') }}">
                @csrf @method("PATCH")
                <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> {{ __('app.cancel_job') }}</button>
            </form>
        @endif
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted w-40pct">{{ __('app.supplier') }}</td>
                            <td><a href="{{ route("resources.suppliers.show", $purchase->supplier) }}" class="text-decoration-none">{{ $purchase->supplier->name }}</a></td>
                        </tr>
                        <tr><td class="text-muted">{{ __('app.order_date') }}</td><td>{{ $purchase->order_date->format(date_fmt()) }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.expected_date') }}</td><td>{{ $purchase->expected_date?->format(date_fmt()) ?? "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.created_by') }}</td><td class="small">{{ $purchase->user->name ?? "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.total') }}</td><td class="fw-semibold">{{ money($purchase->total) }}</td></tr>
                    </tbody>
                </table>
                @if($purchase->notes)
                    <hr>
                    <p class="small mb-0"><strong>{{ __('app.notes') }}:</strong> {{ $purchase->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">{{ __('app.lines') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.item') }}</th>
                                <th class="text-end">{{ __('app.quantity') }}</th>
                                <th class="text-end">{{ __('app.unit_cost') }}</th>
                                <th class="text-end">{{ __('app.subtotal') }}</th>
                                <th class="text-end">{{ __('app.received_qty') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchase->items as $line)
                            <tr>
                                <td>
                                    @if(auth()->user()->isManager())
                                    <a href="{{ route("resources.items.show", $line->item) }}" class="text-decoration-none">{{ $line->item->name }}</a>
                                    @else
                                    {{ $line->item->name }}
                                    @endif
                                    <small class="text-muted">({{ __('app.unit_' . $line->item->unit) }})</small>
                                </td>
                                <td class="text-end small">{{ qty($line->quantity) }}</td>
                                <td class="text-end small">{{ num($line->unit_cost) }}</td>
                                <td class="text-end small">{{ num($line->subtotal) }}</td>
                                <td class="text-end small">
                                    <span class="{{ $line->pendingQty() <= 0 ? "text-success fw-semibold" : "" }}">{{ qty($line->received_qty) }} / {{ qty($line->quantity) }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($purchase->canReceive() && $pendingLines->isNotEmpty() && auth()->user()->isManager())
<div class="card mt-3">
    <div class="card-header"><i class="bi bi-box-arrow-in-down"></i> {{ __('app.po_receive_goods') }}</div>
    <div class="card-body">
        <form action="{{ route("resources.purchases.receive", [$purchase, $pendingLines->first()->id]) }}" method="POST" id="receive-form" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label small">{{ __('app.line') }} <span class="text-danger">*</span></label>
                <select id="receive-line" class="form-select form-select-sm" required>
                    @foreach($pendingLines as $line)
                        <option value="{{ route("resources.purchases.receive", [$purchase, $line->id]) }}">
                            {{ $line->item->name }} — {{ __('app.pending') }}: {{ qty($line->pendingQty()) }} {{ __('app.unit_' . $line->item->unit) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">{{ __('app.quantity') }} <span class="text-danger">*</span></label>
                <input type="number" name="quantity" class="form-control form-control-sm @error('quantity') is-invalid @enderror" min="0.01" step="0.01" value="{{ old("quantity", 1) }}" required>
                @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label small">{{ __('app.unit_cost') }}</label>
                <input type="number" name="unit_cost" class="form-control form-control-sm" min="0" step="0.01" placeholder="{{ __('app.po_cost_default') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small">{{ __('app.lot_number') }}</label>
                <input type="text" name="lot_number" class="form-control form-control-sm @error('lot_number') is-invalid @enderror" value="{{ old("lot_number") }}" maxlength="60">
                @error('lot_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label small">{{ __('app.expiry_date') }}</label>
                <input type="date" name="expires_at" class="form-control form-control-sm" value="{{ old("expires_at") }}">
            </div>
            <div class="col-md-1">
                <button class="btn btn-sm btn-primary w-100" title="{{ __('app.receive') }}"><i class="bi bi-download"></i></button>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="register_expense" value="1" id="receive-register-expense" {{ old("register_expense", true) ? "checked" : "" }}>
                    <label class="form-check-label small" for="receive-register-expense">{{ __('app.register_expense') }}</label>
                </div>
            </div>
        </form>
        <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ __('app.po_receive_hint') }}</div>
    </div>
</div>
@push("scripts")
    @vite("resources/js/pages/purchase-receive.js")
@endpush
@endif

@if($errors->has("status"))
<div class="alert alert-danger mt-3">{{ $errors->first("status") }}</div>
@endif
@endsection
