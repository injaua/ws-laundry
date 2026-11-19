@extends("layouts.admin")
@section("title", __('app.new_supplier'))
@section("page_title", __('app.new_supplier'))

@section("content")
<div class="card">
    <form action="{{ route("resources.suppliers.store") }}" method="POST" class="card-body">
        @csrf
        @include("resources.suppliers._form")
        <div class="mt-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
            <a href="{{ route("resources.suppliers.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
