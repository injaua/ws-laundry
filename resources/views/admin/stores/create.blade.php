@extends("layouts.admin")
@section("title", __('app.store_new'))
@section("page_title", __('app.store_new'))

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-shop-window me-1"></i> {{ __('app.store_new') }}</div>
            <form action="{{ route("admin.stores.store") }}" method="POST">
                @csrf
                <div class="card-body">
                    @include("admin.stores._form")

                    <hr>
                    <h6 class="mb-2">{{ __('app.store_setup') }}</h6>
                    <p class="small text-muted">{{ __('app.store_setup_help') }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('app.store_copy_from') }}</label>
                            <select name="copy_from" class="form-select @error("copy_from") is-invalid @enderror">
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" @selected(old("copy_from") == $template->id)>{{ $template->label() }}</option>
                                @endforeach
                            </select>
                            @error("copy_from")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="copy_catalogue" value="1" id="copyCatalogue" @checked(old("copy_catalogue", true))>
                                <label class="form-check-label" for="copyCatalogue">{{ __('app.store_copy_catalogue') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route("admin.stores.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
