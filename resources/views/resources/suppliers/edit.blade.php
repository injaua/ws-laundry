@extends("layouts.admin")
@section("title", $supplier->name)
@section("page_title", __('app.edit') . ": " . $supplier->name)

@section("content")
<div class="card">
    <form action="{{ route("resources.suppliers.update", $supplier) }}" method="POST" class="card-body">
        @csrf @method("PUT")
        @include("resources.suppliers._form")
        <div class="mt-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
            <a href="{{ route("resources.suppliers.show", $supplier) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
