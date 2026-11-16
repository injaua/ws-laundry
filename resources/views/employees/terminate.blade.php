@extends("layouts.admin")
@section("title", __('app.terminate'))
@section("page_title", __('app.terminate') . " — " . $employee->name)

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-x me-1"></i> {{ __('app.terminate_employee') }}</div>
            <div class="card-body">
                <p class="text-muted small">{{ __('app.termination_user_note') }}</p>
                <form action="{{ route("employees.terminate", $employee) }}" method="POST">
                    @csrf @method("PATCH")
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.termination_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="termination_date" class="form-control @error("termination_date") is-invalid @enderror" value="{{ old("termination_date", date("Y-m-d")) }}" required>
                        @error("termination_date") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.termination_reason') }} <span class="text-danger">*</span></label>
                        <textarea name="termination_reason" class="form-control @error("termination_reason") is-invalid @enderror" rows="3" required>{{ old("termination_reason") }}</textarea>
                        @error("termination_reason") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle"></i> {{ __('app.terminate') }}</button>
                        <a href="{{ route("employees.show", $employee) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
