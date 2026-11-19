@extends("layouts.admin")
@section("title", __('app.edit_promotion'))
@section("page_title", __('app.edit_promotion'))

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body">
                <form action="{{ route("promotions.update", $promotion) }}" method="POST">
                    @csrf @method("PUT")
                    @include("promotions._form")
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
                        <a href="{{ route("promotions.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
