@extends("layouts.admin")
@section("title", __('app.edit_item'))
@section("page_title", __('app.edit_item') . ": " . $item->name)

@section("content")
@php($kind = $item->kind)
<div class="card">
    <form action="{{ route("resources.items.update", $item) }}" method="POST" class="card-body">
        @csrf @method("PUT")
        @include("resources.items._form")
        <div class="mt-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
            <a href="{{ route("resources.items.show", $item) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
