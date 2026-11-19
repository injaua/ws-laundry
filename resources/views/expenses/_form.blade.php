@php($isEdit = !empty($expense) && $expense->exists)
@php($months = __('app.months_short'))
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-{{ $isEdit ? "pencil" : "plus-circle" }} me-1"></i>
                {{ $isEdit ? __('app.edit_expense') : __('app.new_expense') }}
            </div>
            <div class="card-body">
                <form action="{{ $isEdit ? route("expenses.update", $expense) : route("expenses.store") }}" method="POST">
                    @csrf
                    @if($isEdit) @method("PUT") @endif

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.expense_title') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="expenseTitle" class="form-control @error("title") is-invalid @enderror" value="{{ old("title", $expense->title ?? "") }}" required maxlength="200">
                        @error("title") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.category') }} <span class="text-danger">*</span></label>
                        <select name="category" id="expenseCategory" class="form-select @error("category") is-invalid @enderror" required>
                            <option value="">{{ __('app.select_category') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->key }}"
                                    data-fixed="{{ $category->is_fixed ? 1 : 0 }}"
                                    data-default="{{ (int) $category->default_amount }}"
                                    data-salary="{{ $category->is_salary ? 1 : 0 }}"
                                    data-name="{{ $category->name }}"
                                    {{ old("category", $expense->category ?? "") == $category->key ? "selected" : "" }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error("category") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3 d-none" id="fixedPeriodBox">
                        <label class="form-label">{{ __('app.expense_period') }}</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="period_type" id="periodMonthly" value="monthly" {{ old("period_type", $expense->period_type ?? "monthly") == "monthly" ? "checked" : "" }}>
                                <label class="form-check-label" for="periodMonthly">{{ __('app.period_monthly') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="period_type" id="periodOnce" value="once" {{ old("period_type", $expense->period_type ?? "") == "once" ? "checked" : "" }}>
                                <label class="form-check-label" for="periodOnce">{{ __('app.period_once') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 d-none" id="salaryPeriodBox">
                        <label class="form-label">{{ __('app.salary_period') }}</label>
                        <div class="input-group">
                            <select id="salaryMonth" class="form-select">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ now()->month == $m ? "selected" : "" }}>{{ $months[$m - 1] }}</option>
                                @endfor
                            </select>
                            <select id="salaryYear" class="form-select">
                                @for($y = now()->year + 1; $y >= now()->year - 3; $y--)
                                    <option value="{{ $y }}" {{ now()->year == $y ? "selected" : "" }}>{{ $y }}</option>
                                @endfor
                            </select>
                            <button type="button" id="salaryImportBtn" class="btn btn-outline-success">
                                <i class="bi bi-box-arrow-in-down"></i> {{ __('app.import_salary_total') }}
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.amount') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="amount" id="expenseAmount" class="form-control @error("amount") is-invalid @enderror" value="{{ old("amount", $expense->amount ?? "") }}" required min="0">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            @error("amount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control @error("expense_date") is-invalid @enderror" value="{{ old("expense_date", isset($expense) ? $expense->expense_date->format("Y-m-d") : date("Y-m-d")) }}" required>
                        @error("expense_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.description') }}</label>
                        <textarea name="description" class="form-control @error("description") is-invalid @enderror" rows="3">{{ old("description", $expense->description ?? "") }}</textarea>
                        @error("description") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ $isEdit ? __('app.update') : __('app.save') }}</button>
                        <a href="{{ route("expenses.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push("scripts")
    <x-page-data name="expense-form" :data="['totalUrl' => route('expenses.salary-total'), 'isEdit' => (bool) $isEdit]"/>
    @vite("resources/js/pages/expense-form.js")
@endpush
