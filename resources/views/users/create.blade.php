@extends("layouts.admin")
@section("title", __('app.new_user'))
@section("page_title", __('app.new_user'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route("users.store") }}" method="POST" id="userForm" novalidate>
                    @csrf

                    @include("users._fields", ["current" => $currentStoreId])

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route("users.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                        <button type="submit" class="btn btn-primary" data-submit><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
