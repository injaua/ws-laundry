@php($isEdit = !empty($account) && $account->exists)
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-{{ $isEdit ? "pencil" : "plus-circle" }} me-1"></i>
                {{ $isEdit ? __('app.edit_account') : __('app.new_account') }}
            </div>
            <div class="card-body">
                <form action="{{ $isEdit ? route("accounting.accounts.update", $account) : route("accounting.accounts.store") }}" method="POST">
                    @csrf
                    @if($isEdit) @method("PUT") @endif

                    <div class="row g-3">
                        <div class="col-4">
                            <label class="form-label">{{ __('app.account_code') }} <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error("code") is-invalid @enderror" value="{{ old("code", $account->code ?? "") }}" required maxlength="10">
                            @error("code") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-8">
                            <label class="form-label">{{ __('app.account_name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error("name") is-invalid @enderror" value="{{ old("name", $account->name ?? "") }}" required maxlength="120">
                            @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-6">
                            <label class="form-label">{{ __('app.account_type') }} <span class="text-danger">*</span></label>
                            <select name="type" class="form-select @error("type") is-invalid @enderror" required>
                                @foreach(\App\Models\Account::TYPES as $type)
                                    <option value="{{ $type }}" {{ old("type", $account->type ?? "") == $type ? "selected" : "" }}>{{ __('app.account_type_' . $type) }}</option>
                                @endforeach
                            </select>
                            @error("type") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label">{{ __('app.account_parent') }}</label>
                            <select name="parent_id" class="form-select @error("parent_id") is-invalid @enderror">
                                <option value="">—</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}" {{ old("parent_id", $account->parent_id ?? "") == $group->id ? "selected" : "" }}>{{ $group->code }} — {{ $group->name }}</option>
                                @endforeach
                            </select>
                            @error("parent_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_group" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="isGroup" name="is_group" value="1" {{ old("is_group", $account->is_group ?? false) ? "checked" : "" }}>
                            <label class="form-check-label" for="isGroup">{{ __('app.account_is_group') }}</label>
                        </div>
                        @error("is_group") <div class="text-danger small">{{ $message }}</div> @enderror
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active" value="1" {{ old("is_active", $account->is_active ?? true) ? "checked" : "" }}>
                            <label class="form-check-label" for="isActive">{{ __('app.account_active_label') }}</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ $isEdit ? __('app.update') : __('app.save') }}</button>
                        <a href="{{ route("accounting.accounts.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
