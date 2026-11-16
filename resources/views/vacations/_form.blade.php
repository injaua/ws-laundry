@php($isEdit = !empty($vacation) && $vacation->exists)
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-{{ $isEdit ? "pencil" : "umbrella" }} me-1"></i>
                {{ $isEdit ? __('app.edit_vacation') : __('app.new_vacation') }}
            </div>
            <div class="card-body">
                <form action="{{ $isEdit ? route("vacations.update", $vacation) : route("vacations.store") }}" method="POST">
                    @csrf
                    @if($isEdit) @method("PUT") @endif

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.employee') }} <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select @error("employee_id") is-invalid @enderror" required>
                            <option value="">{{ __('app.employees') }}</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ (int) old("employee_id", $vacation->employee_id ?? 0) === $employee->id ? "selected" : "" }}>
                                    {{ $employee->name }} — {{ $employee->position }}
                                </option>
                            @endforeach
                        </select>
                        @error("employee_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.start_date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control @error("start_date") is-invalid @enderror" value="{{ old("start_date", isset($vacation) ? $vacation->start_date->format("Y-m-d") : "") }}" required>
                            @error("start_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.end_date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control @error("end_date") is-invalid @enderror" value="{{ old("end_date", isset($vacation) ? $vacation->end_date->format("Y-m-d") : "") }}" required>
                            @error("end_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.vacation_type') }} <span class="text-danger">*</span></label>
                        <select name="type" class="form-select @error("type") is-invalid @enderror" required>
                            @foreach(["vacation", "license"] as $type)
                                <option value="{{ $type }}" {{ old("type", $vacation->type ?? "vacation") == $type ? "selected" : "" }}>{{ __('app.vacation_type_' . $type) }}</option>
                            @endforeach
                        </select>
                        @error("type") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    @if($isEdit)
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.status') }}</label>
                        <select name="status" class="form-select @error("status") is-invalid @enderror">
                            @foreach(\App\Models\Vacation::STATUSES as $st)
                                <option value="{{ $st }}" {{ old("status", $vacation->status) == $st ? "selected" : "" }}>{{ __('app.' . $st) }}</option>
                            @endforeach
                        </select>
                        @error("status") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.notes') }}</label>
                        <textarea name="notes" class="form-control @error("notes") is-invalid @enderror" rows="2">{{ old("notes", $vacation->notes ?? "") }}</textarea>
                        @error("notes") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ $isEdit ? __('app.update') : __('app.save') }}</button>
                        <a href="{{ route("vacations.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
