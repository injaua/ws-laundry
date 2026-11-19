@extends("layouts.admin")
@section("title", __('app.my_till'))
@section("page_title", __('app.open_till'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-safe me-1"></i> {{ __('app.open_till') }}</div>
            <div class="card-body">
                <p class="text-muted small">{{ __('app.open_till_help') }}</p>
                <form action="{{ route("cash-sessions.store") }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.opening_float') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="opening_float" class="form-control @error("opening_float") is-invalid @enderror" value="{{ old("opening_float", 0) }}" min="0" step="1" required autofocus>
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            @error("opening_float") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text">{{ __('app.opening_float_help') }}</div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-unlock"></i> {{ __('app.open_till') }}
                    </button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="{{ route("cash-sessions.index") }}" class="small"><i class="bi bi-clock-history"></i> {{ __('app.till_history') }}</a>
        </div>
    </div>
</div>
@endsection
