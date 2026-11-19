@extends("layouts.admin")
@section("title", __('app.edit_customer'))
@section("page_title", __('app.edit_customer'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person me-1"></i> {{ __('app.edit_customer') }}
            </div>
            <div class="card-body">
                <form action="{{ route("customers.update", $customer) }}" method="POST">
                    @csrf @method("PUT")
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.full_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error("name") is-invalid @enderror" value="{{ old("name", $customer->name) }}" required>
                        @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.phone') }} <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control @error("phone") is-invalid @enderror" value="{{ old("phone", $customer->phone) }}" required>
                        @error("phone") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-check mt-2">
                            <input type="hidden" name="whatsapp_opt_out" value="0">
                            <input class="form-check-input" type="checkbox" name="whatsapp_opt_out" value="1" id="whatsapp_opt_out" @checked(old("whatsapp_opt_out", $customer->whatsapp_opt_out))>
                            <label class="form-check-label small" for="whatsapp_opt_out">{{ __('app.wa_opt_out_label') }}</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.address') }}</label>
                        <textarea name="address" class="form-control @error("address") is-invalid @enderror" rows="3">{{ old("address", $customer->address) }}</textarea>
                        @error("address") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    @include("customers._delivery")
                    @include("customers._business")
                    @include("customers._credit")
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> {{ __('app.update') }}
                        </button>
                        <a href="{{ route("customers.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
