@extends("layouts.admin")
@section("title", $maintenance->title)
@section("page_title", __('app.maintenance') . ": " . $maintenance->title)

@section("content")
<div class="page-title">
    <span>
        <span class="badge badge-status bg-{{ $maintenance->status_color }}">{{ __('app.maintenance_status_' . $maintenance->status) }}</span>
        <span class="badge bg-light text-dark ms-1">{{ __('app.maintenance_kind_' . $maintenance->kind) }}</span>
    </span>
    <div>
        @if(!in_array($maintenance->status, ["cancelled"]))
            <form action="{{ route("resources.maintenance.cancel", $maintenance) }}" method="POST" class="d-inline" data-confirm="{{ __('app.cancel_job_confirm') }}">
                @csrf @method("PATCH")
                <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> {{ __('app.cancel_job') }}</button>
            </form>
        @endif
        <a href="{{ route("resources.maintenance.edit", $maintenance) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('app.edit') }}</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="text-muted w-40pct">{{ __('app.equipment') }}</td><td><a href="{{ route("resources.equipments.show", $maintenance->equipment) }}" class="text-decoration-none">{{ $maintenance->equipment->name }}</a></td></tr>
                        <tr><td class="text-muted">{{ __('app.service_date') }}</td><td>{{ $maintenance->service_date->format(date_fmt()) }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.next_service_date') }}</td><td>{{ $maintenance->next_service_date ? $maintenance->next_service_date->format(date_fmt()) : "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.technician') }}</td><td>{{ $maintenance->technician ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.employee') }}</td><td>{{ $maintenance->employee->name ?? "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.cost') }}</td><td class="fw-semibold">{{ money($maintenance->cost) }}</td></tr>
                        <tr>
                            <td class="text-muted">{{ __('app.expense') }}</td>
                            <td>
                                @if($maintenance->expense_id)
                                    <a href="{{ route("expenses.index", ["search" => $maintenance->expense->title]) }}" class="text-decoration-none"><span class="badge bg-info-subtle text-info"><i class="bi bi-cash-stack"></i> {{ __('app.linked_expense') }}</span></a>
                                @else
                                    <span class="text-muted small">{{ __('app.not_registered') }}</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
                @if($maintenance->fault_description || $maintenance->description)
                    <hr>
                    @if($maintenance->fault_description)
                        <p class="small"><strong>{{ __('app.fault_description') }}:</strong> {{ $maintenance->fault_description }}</p>
                    @endif
                    @if($maintenance->description)
                        <p class="small mb-0"><strong>{{ __('app.description') }}:</strong> {{ $maintenance->description }}</p>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">{{ __('app.job_parts') }}</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.name') }}</th>
                            <th class="text-end">{{ __('app.quantity') }}</th>
                            <th class="text-center">{{ __('app.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($maintenance->parts as $part)
                        <tr>
                            <td>
                                <a href="{{ route("resources.items.show", $part->item) }}" class="text-decoration-none">{{ $part->item->name }}</a>
                                <small class="text-muted">({{ __('app.unit_' . $part->item->unit) }})</small>
                            </td>
                            <td class="text-end">{{ qty($part->quantity) }}</td>
                            <td class="text-center">
                                @if($maintenance->status !== "cancelled")
                                <form action="{{ route("resources.maintenance.parts.remove", [$maintenance, $part]) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_confirm') }}">
                                    @csrf @method("DELETE")
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.remove_part') }}"><i class="bi bi-dash-lg"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center py-3 text-muted small">{{ __('app.no_parts_used') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($maintenance->status !== "cancelled")
            <div class="card-footer">
                <form action="{{ route("resources.maintenance.parts.add", $maintenance) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small">{{ __('app.spare_parts') }}</label>
                        <select name="inventory_item_id" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach($availableParts as $partOption)
                                <option value="{{ $partOption->id }}" {{ old("inventory_item_id") == $partOption->id ? "selected" : "" }}>
                                    {{ $partOption->name }} ({{ qty((float) $partOption->stock_qty) }} {{ __('app.unit_' . $partOption->unit) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">{{ __('app.quantity') }}</label>
                        <input type="number" name="quantity" class="form-control form-control-sm" min="0.01" step="0.01" value="{{ old("quantity", 1) }}" required>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> {{ __('app.add_part') }}</button>
                    </div>
                </form>
                @if($availableParts->isEmpty())
                    <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ __('app.no_parts_available') }}</div>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
