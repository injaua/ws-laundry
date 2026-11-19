@extends("layouts.admin")
@section("title", __('app.new_promotion'))
@section("page_title", __('app.new_promotion'))

@section("content")
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body">
                <form action="{{ route("promotions.store") }}" method="POST">
                    @csrf 
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
