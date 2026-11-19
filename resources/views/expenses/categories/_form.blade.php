@php($isEdit = !empty($category) && $category->exists)
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-{{ $isEdit ? "pencil" : "plus-circle" }} me-1"></i>
                {{ $isEdit ? __('app.edit_category') : __('app.new_category') }}
            </div>
            <div class="card-body">
                <form action="{{ $isEdit ? route("expense-categories.update", $category) : route("expense-categories.store") }}" method="POST">
                    @csrf
                    @if($isEdit) @method("PUT") @endif

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.category_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error("name") is-invalid @enderror" value="{{ old("name", $category->name ?? "") }}" required maxlength="100">
                        @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.category_color') }} <span class="text-danger">*</span></label>
                        <select name="color" class="form-select @error("color") is-invalid @enderror" required>
                            @foreach(\App\Models\ExpenseCategory::COLORS as $color)
                                <option value="{{ $color }}" {{ old("color", $category->color ?? "secondary") == $color ? "selected" : "" }}>{{ $color }}</option>
                            @endforeach
                        </select>
                        @error("color") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.expense_account') }} <span class="text-danger">*</span></label>
                        <select name="account_id" class="form-select @error("account_id") is-invalid @enderror" required>
                            <option value="" disabled {{ empty(old("account_id", $category->account_id ?? null)) ? "selected" : "" }}>{{ __('app.select_account') }}</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ old("account_id", $category->account_id ?? "") == $account->id ? "selected" : "" }}>{{ $account->code }} — {{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error("account_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">{{ __('app.expense_account_hint') }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_fixed" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="isFixed" name="is_fixed" value="1" {{ old("is_fixed", $category->is_fixed ?? false) ? "checked" : "" }}>
                            <label class="form-check-label" for="isFixed">{{ __('app.category_fixed') }}</label>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_salary" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="isSalary" name="is_salary" value="1" {{ old("is_salary", $category->is_salary ?? false) ? "checked" : "" }}>
                            <label class="form-check-label" for="isSalary">{{ __('app.category_salary') }}</label>
                        </div>
                        @error("is_salary") <div class="text-danger small">{{ $message }}</div> @enderror
                        <div class="form-text">{{ __('app.category_salary_hint') }}</div>
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="is_vat_applicable" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="isVatApplicable" name="is_vat_applicable" value="1" {{ old("is_vat_applicable", $category->is_vat_applicable ?? false) ? "checked" : "" }}>
                            <label class="form-check-label" for="isVatApplicable">{{ __('app.vat_applicable') }}</label>
                        </div>
                        <div class="form-text">{{ __('app.vat_applicable_hint') }}</div>
                    </div>

                    <div class="mb-3 {{ old("is_fixed", $category->is_fixed ?? false) ? "" : "d-none" }}" id="defaultAmountBox" data-show-if="is_fixed">
                        <label class="form-label">{{ __('app.category_default_amount') }}</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="default_amount" class="form-control @error("default_amount") is-invalid @enderror" value="{{ old("default_amount", isset($category) ? (float) $category->default_amount : "") }}" min="0">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            @error("default_amount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ $isEdit ? __('app.update') : __('app.save') }}</button>
                        <a href="{{ route("expense-categories.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

