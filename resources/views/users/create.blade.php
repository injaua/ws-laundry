@extends("layouts.admin")
@section("title", __('app.new_user'))
@section("page_title", __('app.new_user'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route("users.store") }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old("name") }}" class="form-control @error("name") is-invalid @enderror" required autofocus>
                        @error("name")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.email') }} <span class="text-danger">*</span></label>
                        <input type="email" name="email" value="{{ old("email") }}" class="form-control @error("email") is-invalid @enderror" required>
                        @error("email")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.role') }} <span class="text-danger">*</span></label>
                        <select name="role" class="form-select @error("role") is-invalid @enderror" required>
                            @foreach($roles as $role)
                                <option value="{{ $role }}" @selected(old("role") == $role)>{{ __('app.role_' . $role) }}</option>
                            @endforeach
                        </select>
                        @error("role")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.password') }} <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control @error("password") is-invalid @enderror" required autocomplete="new-password">
                        @error("password")
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('app.confirm_password') }} <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route("users.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
