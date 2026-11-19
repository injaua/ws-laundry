@php($isEdit = !empty($salary) && $salary->exists)
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-{{ $isEdit ? "pencil" : "plus-circle" }} me-1"></i>
                {{ $isEdit ? __('app.edit_payslip') : __('app.new_payslip') }}
            </div>
            <div class="card-body">
                <form action="{{ $isEdit ? route("salaries.update", $salary) : route("salaries.store") }}" method="POST">
                    @csrf
                    @if($isEdit) @method("PUT") @endif

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.employee') }} <span class="text-danger">*</span></label>
                        <select name="employee_id" id="payslipEmployee" class="form-select @error("employee_id") is-invalid @enderror" required data-base-field="base_salary">
                            <option value="">{{ __('app.employees') }}</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" data-base="{{ (int) $employee->base_salary }}"
                                    {{ (int) old("employee_id", $salary->employee_id ?? 0) === $employee->id ? "selected" : "" }}>
                                    {{ $employee->name }} — {{ $employee->position }}
                                </option>
                            @endforeach
                        </select>
                        @error("employee_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.period_month') }} <span class="text-danger">*</span></label>
                            <select name="period_month" id="periodMonth" class="form-select @error("period_month") is-invalid @enderror" required>
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ (int) old("period_month", $salary->period_month ?? now()->month) === $m ? "selected" : "" }}>{{ sprintf("%02d", $m) }}</option>
                                @endfor
                            </select>
                            @error("period_month") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.period_year') }} <span class="text-danger">*</span></label>
                            <input type="number" name="period_year" id="periodYear" class="form-control @error("period_year") is-invalid @enderror" value="{{ old("period_year", $salary->period_year ?? now()->year) }}" required min="2000" max="2100">
                            @error("period_year") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.base_salary') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="base_salary" id="baseSalary" class="form-control @error("base_salary") is-invalid @enderror" value="{{ old("base_salary", $salary->base_salary ?? 0) }}" required min="0">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            @error("base_salary") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.bonus') }}</label>
                            <div class="input-group">
                                <input type="number" name="bonus" id="bonusField" class="form-control @error("bonus") is-invalid @enderror" value="{{ old("bonus", $salary->bonus ?? 0) }}" min="0">
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                                @error("bonus") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('app.deduction') }}</label>
                            <div class="input-group">
                                <input type="number" name="deduction" id="deductionField" class="form-control @error("deduction") is-invalid @enderror" value="{{ old("deduction", $salary->deduction ?? 0) }}" min="0">
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                                @error("deduction") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.absence_deduction') }}</label>
                        <div class="input-group">
                            <input type="number" name="absence_deduction" id="absenceField" class="form-control @error("absence_deduction") is-invalid @enderror" value="{{ old("absence_deduction", $salary->absence_deduction ?? 0) }}" min="0">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            @error("absence_deduction") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div id="absenceHint" class="form-text"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.net_salary') }}</label>
                        <div class="input-group">
                            <input type="text" id="netSalary" class="form-control fw-bold" readonly value="{{ num(($salary->net_salary ?? 0)) }}">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.notes') }}</label>
                        <textarea name="notes" class="form-control @error("notes") is-invalid @enderror" rows="2">{{ old("notes", $salary->notes ?? "") }}</textarea>
                        @error("notes") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ $isEdit ? __('app.update') : __('app.save') }}</button>
                        <a href="{{ route("salaries.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push("scripts")
    <x-page-data name="payslip-form" :data="['suggestionUrl' => route('salaries.absence-suggestion'), 'hintText' => __('app.absence_deduction_hint')]"/>
    @vite("resources/js/pages/payslip-form.js")
@endpush
