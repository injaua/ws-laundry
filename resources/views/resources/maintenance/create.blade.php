@extends("layouts.admin")
@section("title", __('app.new_maintenance'))
@section("page_title", __('app.new_maintenance'))

@section("content")
<div class="card">
    <form action="{{ route("resources.maintenance.store") }}" method="POST" class="card-body">
        @csrf
        @include("resources.maintenance._form")
        <div class="mt-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
            <a href="{{ route("resources.maintenance.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
