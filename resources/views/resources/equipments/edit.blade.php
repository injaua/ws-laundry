@extends("layouts.admin")
@section("title", __('app.edit_equipment'))
@section("page_title", __('app.edit_equipment'))

@section("content")
<div class="card">
    <form action="{{ route("resources.equipments.update", $equipment) }}" method="POST" enctype="multipart/form-data" class="card-body">
        @csrf @method("PUT")
        @include("resources.equipments._form")
        <div class="mt-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
            <a href="{{ route("resources.equipments.show", $equipment) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
