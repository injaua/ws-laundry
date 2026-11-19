@extends("layouts.admin")
@section("title", __('app.new_purchase_order'))
@section("page_title", __('app.new_purchase_order'))

@section("content")
<div class="card">
    <form action="{{ route("resources.purchases.store") }}" method="POST" class="card-body">
        @csrf
        @include("resources.purchases._form", ["purchase" => null])
        <div class="mt-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
            <a href="{{ route("resources.purchases.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
