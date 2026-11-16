@php($isEdit = !empty($employee) && $employee->exists)
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-{{ $isEdit ? "pencil" : "plus-circle" }} me-1"></i>
                {{ $isEdit ? __('app.edit_employee') : __('app.new_employee') }}
            </div>
            <div class="card-body">
                <form action="{{ $isEdit ? route("employees.update", $employee) : route("employees.store") }}" method="POST">
                    @csrf
                    @if($isEdit) @method("PUT") @endif

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error("name") is-invalid @enderror" value="{{ old("name", $employee->name ?? "") }}" required maxlength="100">
                        @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.bi_number') }}</label>
                            <input type="text" name="bi_number" class="form-control @error("bi_number") is-invalid @enderror" value="{{ old("bi_number", $employee->bi_number ?? "") }}" maxlength="30">
                            @error("bi_number") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.phone') }}</label>
                            <input type="text" name="phone" class="form-control @error("phone") is-invalid @enderror" value="{{ old("phone", $employee->phone ?? "") }}" maxlength="30">
                            @error("phone") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.position') }} <span class="text-danger">*</span></label>
                        <input type="text" name="position" class="form-control @error("position") is-invalid @enderror" value="{{ old("position", $employee->position ?? "") }}" required maxlength="100">
                        @error("position") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.hire_date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="hire_date" class="form-control @error("hire_date") is-invalid @enderror" value="{{ old("hire_date", isset($employee) ? $employee->hire_date?->format("Y-m-d") : date("Y-m-d")) }}" required>
                            @error("hire_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.probation_ends_at') }}</label>
                            <input type="date" name="probation_ends_at" class="form-control @error("probation_ends_at") is-invalid @enderror" value="{{ old("probation_ends_at", isset($employee) ? $employee->probation_ends_at?->format("Y-m-d") : "") }}">
                            @error("probation_ends_at") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.birth_date') }}</label>
                            <input type="date" name="birth_date" class="form-control @error("birth_date") is-invalid @enderror" value="{{ old("birth_date", isset($employee) ? $employee->birth_date?->format("Y-m-d") : "") }}">
                            @error("birth_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.contract_type') }} <span class="text-danger">*</span></label>
                            <select name="contract_type" class="form-select @error("contract_type") is-invalid @enderror" required>
                                @foreach(["full_time", "part_time", "fixed_term"] as $ct)
                                    <option value="{{ $ct }}" {{ old("contract_type", $employee->contract_type ?? "full_time") == $ct ? "selected" : "" }}>{{ __('app.contract_' . $ct) }}</option>
                                @endforeach
                            </select>
                            @error("contract_type") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.base_salary') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="base_salary" class="form-control @error("base_salary") is-invalid @enderror" value="{{ old("base_salary", isset($employee) ? (int) $employee->base_salary : "") }}" required min="0">
                            <span class="input-group-text">Mts</span>
                            @error("base_salary") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.link_user') }}</label>
                        <select name="user_id" class="form-select @error("user_id") is-invalid @enderror">
                            <option value="">{{ __('app.link_user_none') }}</option>
                            @foreach($linkableUsers as $user)
                                <option value="{{ $user->id }}" {{ (int) old("user_id", $employee->user_id ?? 0) === $user->id ? "selected" : "" }}>
                                    {{ $user->name }} ({{ __('app.role_' . $user->role) }})
                                </option>
                            @endforeach
                        </select>
                        @error("user_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.address') }}</label>
                        <input type="text" name="address" class="form-control @error("address") is-invalid @enderror" value="{{ old("address", $employee->address ?? "") }}">
                        @error("address") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.notes') }}</label>
                        <textarea name="notes" class="form-control @error("notes") is-invalid @enderror" rows="2">{{ old("notes", $employee->notes ?? "") }}</textarea>
                        @error("notes") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ $isEdit ? __('app.update') : __('app.save') }}</button>
                        <a href="{{ route("employees.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
