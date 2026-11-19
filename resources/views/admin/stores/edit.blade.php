@extends("layouts.admin")
@section("title", __('app.store_edit'))
@section("page_title", __('app.store_edit'))

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-shop-window me-1"></i> {{ $store->label() }}</div>
            <form action="{{ route("admin.stores.update", $store) }}" method="POST">
                @csrf @method("PUT")
                <div class="card-body">
                    @include("admin.stores._form")
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
