@php($maintenance = $maintenance ?? null)
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.equipment') }} <span class="text-danger">*</span></label>
        <select name="equipment_id" class="form-select @error('equipment_id') is-invalid @enderror" required>
            @foreach($equipments as $equipment)
                <option value="{{ $equipment->id }}" {{ old("equipment_id", $maintenance?->equipment_id ?? request("equipment_id")) == $equipment->id ? "selected" : "" }}>{{ $equipment->name }}@if($equipment->code) ({{ $equipment->code }})@endif</option>
            @endforeach
        </select>
        @error('equipment_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.type') }} <span class="text-danger">*</span></label>
        <select name="kind" class="form-select" required>
            @foreach(\App\Models\MaintenanceJob::KINDS as $kd)
                <option value="{{ $kd }}" {{ old("kind", $maintenance?->kind ?? "corrective") == $kd ? "selected" : "" }}>{{ __('app.maintenance_kind_' . $kd) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.status') }} <span class="text-danger">*</span></label>
        <select name="status" class="form-select" required>
            @foreach(\App\Models\MaintenanceJob::STATUSES as $st)
                <option value="{{ $st }}" {{ old("status", $maintenance?->status ?? "scheduled") == $st ? "selected" : "" }}>{{ __('app.maintenance_status_' . $st) }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.title') }} <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $maintenance?->title) }}" required>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.service_date') }} <span class="text-danger">*</span></label>
        <input type="date" name="service_date" class="form-control" value="{{ old('service_date', $maintenance?->service_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.next_service_date') }}</label>
        <input type="date" name="next_service_date" class="form-control" value="{{ old('next_service_date', $maintenance?->next_service_date?->format('Y-m-d')) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.technician') }}</label>
        <input type="text" name="technician" class="form-control" value="{{ old('technician', $maintenance?->technician) }}" placeholder="{{ __('app.technician_hint') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.employee') }}</label>
        <select name="employee_id" class="form-select">
            <option value="">—</option>
            @foreach($employees as $employee)
                <option value="{{ $employee->id }}" {{ old("employee_id", $maintenance?->employee_id) == $employee->id ? "selected" : "" }}>{{ $employee->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.cost') }}</label>
        <div class="input-group">
            <input type="number" name="cost" class="form-control" min="0" step="1" value="{{ old('cost', $maintenance?->cost ?? 0) }}">
            <span class="input-group-text">Mts</span>
        </div>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="register_expense" value="1" id="register_expense" {{ old("register_expense", (bool) ($maintenance?->expense_id ?? true)) ? "checked" : "" }}>
            <label class="form-check-label small" for="register_expense">{{ __('app.register_expense') }}</label>
        </div>
        <div class="mt-1">
            <label class="form-label small">{{ __('app.expense_category') }}</label>
            <select name="expense_category" class="form-select form-select-sm" style="max-width:280px">
                @foreach($categories as $category)
                    <option value="{{ $category->key }}" {{ old("expense_category", $maintenance?->expense->category ?? "manutencao") == $category->key ? "selected" : "" }}>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.fault_description') }}</label>
        <textarea name="fault_description" rows="3" class="form-control">{{ old('fault_description', $maintenance?->fault_description) }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label">{{ __('app.description') }}</label>
        <textarea name="description" rows="3" class="form-control">{{ old('description', $maintenance?->description) }}</textarea>
    </div>
</div>
