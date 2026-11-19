@php($isEdit = !empty($entry) && $entry->exists)
@php($defaults = $defaults ?? [])
<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-{{ $isEdit ? "pencil" : "journal-plus" }} me-1"></i>
                {{ $isEdit ? __('app.edit_journal_entry') : __('app.new_journal_entry') }}
            </div>
            <div class="card-body">
                <form action="{{ $isEdit ? route("accounting.journal-entries.update", $entry) : route("accounting.journal-entries.store") }}" method="POST">
                    @csrf
                    @if($isEdit) @method("PUT") @endif

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('app.entry_date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" class="form-control @error("entry_date") is-invalid @enderror" value="{{ old("entry_date", isset($entry) ? $entry->entry_date->format("Y-m-d") : now()->toDateString()) }}" required>
                            @error("entry_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">{{ __('app.description') }} <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control @error("description") is-invalid @enderror" value="{{ old("description", $entry->description ?? "") }}" required maxlength="255">
                            @error("description") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <hr>

                    @error("pairs") <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

                    @for($i = 0; $i < 3; $i++)
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-md-4">
                            <select name="pairs[{{ $i }}][debit_account_id]" class="form-select">
                                <option value="">{{ __('app.debit_account') }}</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ old("pairs.$i.debit_account_id", $defaults[$i]["debit_account_id"] ?? "") == $account->id ? "selected" : "" }}>{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="pairs[{{ $i }}][debit_amount]" class="form-control" value="{{ old("pairs.$i.debit_amount", $defaults[$i]["debit_amount"] ?? "") }}" min="1" step="0.01" placeholder="{{ __('app.debit') }}">
                        </div>
                        <div class="col-md-1 text-center text-muted"><i class="bi bi-arrow-left-right"></i></div>
                        <div class="col-md-3">
                            <select name="pairs[{{ $i }}][credit_account_id]" class="form-select">
                                <option value="">{{ __('app.credit_account') }}</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ old("pairs.$i.credit_account_id", $defaults[$i]["credit_account_id"] ?? "") == $account->id ? "selected" : "" }}>{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="pairs[{{ $i }}][credit_amount]" class="form-control" value="{{ old("pairs.$i.credit_amount", $defaults[$i]["credit_amount"] ?? "") }}" min="1" step="0.01" placeholder="{{ __('app.credit') }}">
                        </div>
                    </div>
                    @endfor
                    <div class="form-text mb-3">{{ __('app.journal_total_debit') }} = {{ __('app.journal_total_credit') }}</div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ $isEdit ? __('app.update') : __('app.save') }}</button>
                        <a href="{{ route("accounting.journal-entries.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
