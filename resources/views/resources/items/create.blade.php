@extends("layouts.admin")
@section("title", __('app.new_item'))
@section("page_title", __('app.new_item'))

@section("content")
<div class="card">
    <form action="{{ route("resources.items.store") }}" method="POST" class="card-body">
        @csrf
        <input type="hidden" name="kind" value="{{ $kind }}">
        @include("resources.items._form")
        <div class="mt-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
            <a href="{{ route("resources.items.index", ["kind" => $kind]) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        </div>
        <div class="alert alert-info mt-3 mb-0 small">
            <i class="bi bi-info-circle"></i> {{ __('app.stock_zero_hint') }}
        </div>
    </form>
</div>
@endsection
