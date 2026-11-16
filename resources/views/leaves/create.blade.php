@extends("layouts.admin")
@section("title", __('app.new_dispensation'))
@section("page_title", __('app.new_dispensation'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-slash-circle me-1"></i> {{ __('app.new_dispensation') }}</div>
            <div class="card-body">
                <form action="{{ route("leaves.store") }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.employee') }} <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select @error("employee_id") is-invalid @enderror" required>
                            <option value="">{{ __('app.employees') }}</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ (int) old("employee_id") === $employee->id ? "selected" : "" }}>
                                    {{ $employee->name }} — {{ $employee->position }}
                                </option>
                            @endforeach
                        </select>
                        @error("employee_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">{{ __('app.leave_date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="leave_date" class="form-control @error("leave_date") is-invalid @enderror" value="{{ old("leave_date", date("Y-m-d")) }}" required>
                            @error("leave_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">{{ __('app.hours') }} <span class="text-danger">*</span></label>
                            <input type="number" name="hours" class="form-control @error("hours") is-invalid @enderror" value="{{ old("hours", 8) }}" required min="1" max="24">
                            @error("hours") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.reason') }} <span class="text-danger">*</span></label>
                        <input type="text" name="reason" class="form-control @error("reason") is-invalid @enderror" value="{{ old("reason") }}" required maxlength="255">
                        @error("reason") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
                        <a href="{{ route("leaves.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
