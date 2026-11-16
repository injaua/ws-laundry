@extends("layouts.admin")
@section("title", __('app.new_justification'))
@section("page_title", __('app.absence_justifications'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-file-earmark-text me-1"></i> {{ __('app.new_justification') }}
            </div>
            <div class="card-body">
                <form action="{{ route("justifications.store") }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.employee') }} <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select @error("employee_id") is-invalid @enderror" required>
                            <option value="">{{ __('app.employees') }}</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ (int) old("employee_id", $prefill["employee_id"]) === $employee->id ? "selected" : "" }}>
                                    {{ $employee->name }} — {{ $employee->position }}
                                </option>
                            @endforeach
                        </select>
                        @error("employee_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.absence_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="absence_date" class="form-control @error("absence_date") is-invalid @enderror"
                               value="{{ old("absence_date", $prefill["date"]) }}" required>
                        @error("absence_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.reason') }} <span class="text-danger">*</span></label>
                        <input type="text" name="reason" class="form-control @error("reason") is-invalid @enderror" value="{{ old("reason") }}" required maxlength="255">
                        @error("reason") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old("notes") }}</textarea>
                    </div>

                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i> {{ __('app.justification_approve_hint') }}
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
                        <a href="{{ route("justifications.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
