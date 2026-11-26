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
                                    data-default="{{ (float) $category->default_amount }}"
                                    data-salary="{{ $category->is_salary ? 1 : 0 }}"
                                    data-vat="{{ \App\Models\Expense::defaultVatRate($category) }}"
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
                            <input type="number" step="0.01" name="amount" id="expenseAmount" class="form-control @error("amount") is-invalid @enderror" value="{{ old("amount", $expense->amount ?? "") }}" required min="0">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            @error("amount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text">{{ __('app.expense_amount_gross_hint') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.expense_vat_rate') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="vat_rate" id="expenseVatRate" class="form-control @error("vat_rate") is-invalid @enderror" value="{{ old("vat_rate", $isEdit ? (float) $expense->effectiveVatRate() : "") }}" required min="0" max="100">
                            <span class="input-group-text">%</span>
                            @error("vat_rate") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text" id="expenseVatPreview"></div>
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

                    @unless($isEdit)
                    @php($poOn = (bool) old("create_purchase_order"))
                    @php($poLines = old("po_lines", [["kind" => "product", "name" => "", "unit" => "un", "quantity" => 1, "unit_cost" => ""]]))
                    <div class="border rounded p-3 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="create_purchase_order" value="1" id="createPurchaseOrder" {{ $poOn ? "checked" : "" }}>
                            <label class="form-check-label fw-semibold" for="createPurchaseOrder">{{ __('app.expense_create_po') }}</label>
                        </div>
                        <div class="form-text">{{ __('app.expense_create_po_hint') }}</div>

                        <div id="purchaseOrderBox" class="mt-3 {{ $poOn ? "" : "d-none" }}">
                            <div class="mb-3">
                                <label class="form-label">{{ __('app.supplier') }} <span class="text-danger">*</span></label>
                                <select name="po_supplier_id" class="form-select @error("po_supplier_id") is-invalid @enderror" data-po-required>
                                    <option value="">{{ __('app.choose') }}</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ old("po_supplier_id") == $supplier->id ? "selected" : "" }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                @error("po_supplier_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if($suppliers->isEmpty())
                                    <div class="form-text text-warning">{{ __('app.expense_po_no_suppliers') }}</div>
                                @endif
                            </div>

                            <label class="form-label">{{ __('app.lines') }} <span class="text-danger">*</span></label>
                            @error("po_lines") <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                            <div id="poLines">
                                @foreach($poLines as $i => $line)
                                <div class="po-line border rounded p-2 mb-2">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <select name="po_lines[{{ $i }}][kind]" class="form-select form-select-sm po-kind" data-po-required>
                                                @foreach(\App\Actions\Expenses\RaisePurchaseOrder::KINDS as $kind)
                                                    <option value="{{ $kind }}" {{ ($line["kind"] ?? "product") === $kind ? "selected" : "" }}>{{ __('app.po_line_kind_' . $kind) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="po_lines[{{ $i }}][name]" class="form-control form-control-sm po-name @error("po_lines.$i.name") is-invalid @enderror" value="{{ $line["name"] ?? "" }}" maxlength="150" placeholder="{{ __('app.name') }}" autocomplete="off" data-po-required>
                                        </div>
                                        <div class="col-4">
                                            <select name="po_lines[{{ $i }}][unit]" class="form-select form-select-sm po-unit">
                                                @foreach(\App\Models\InventoryItem::UNITS as $unit)
                                                    <option value="{{ $unit }}" {{ ($line["unit"] ?? "un") === $unit ? "selected" : "" }}>{{ __('app.unit_' . $unit) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-3">
                                            <input type="number" name="po_lines[{{ $i }}][quantity]" class="form-control form-control-sm po-qty @error("po_lines.$i.quantity") is-invalid @enderror" value="{{ $line["quantity"] ?? 1 }}" min="0.01" step="0.01" placeholder="{{ __('app.quantity') }}" title="{{ __('app.quantity') }}" data-po-required>
                                        </div>
                                        <div class="col-4">
                                            <input type="number" name="po_lines[{{ $i }}][unit_cost]" class="form-control form-control-sm po-cost @error("po_lines.$i.unit_cost") is-invalid @enderror" value="{{ $line["unit_cost"] ?? "" }}" min="0" step="0.01" placeholder="{{ __('app.unit_cost') }}" title="{{ __('app.unit_cost') }}" data-po-required>
                                        </div>
                                        <div class="col-1 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger po-remove" title="{{ __('app.remove') }}"><i class="bi bi-x"></i></button>
                                        </div>
                                    </div>
                                    <div class="small mt-1 po-status"></div>
                                    @foreach(["name", "quantity", "unit_cost", "kind"] as $f)
                                        @error("po_lines.$i.$f") <div class="text-danger small">{{ $message }}</div> @enderror
                                    @endforeach
                                </div>
                                @endforeach
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" id="poAddLine" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus"></i> {{ __('app.add_line') }}</button>
                                <span class="small text-muted">{{ __('app.total') }}: <strong id="poTotal">0.00</strong> {{ currency_symbol() }}</span>
                            </div>
                            @foreach(\App\Actions\Expenses\RaisePurchaseOrder::KINDS as $kind)
                                <datalist id="poNames-{{ $kind }}">
                                    @foreach($resourceNames[$kind] ?? [] as $resource)
                                        <option value="{{ $resource["name"] }}"></option>
                                    @endforeach
                                </datalist>
                            @endforeach
                        </div>
                    </div>
                    @endunless

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
    <x-page-data name="expense-form" :data="['totalUrl' => route('expenses.salary-total'), 'isEdit' => (bool) $isEdit, 'vatLabel' => __('app.expense_vat_preview'), 'currency' => currency_symbol(), 'resources' => $resourceNames ?? [], 'existingLabel' => __('app.po_line_existing'), 'newLabel' => __('app.po_line_new'), 'newEquipmentLabel' => __('app.po_line_new_equipment')]"/>
    @vite("resources/js/pages/expense-form.js")
@endpush
