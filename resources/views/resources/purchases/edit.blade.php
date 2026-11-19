@extends("layouts.admin")
@section("title", $purchase->po_number)
@section("page_title", __('app.edit') . ": " . $purchase->po_number)

@section("content")
<div class="card">
    <form action="{{ route("resources.purchases.update", $purchase) }}" method="POST" class="card-body">
        @csrf @method("PUT")
        @include("resources.purchases._form")
        <div class="mt-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
            <a href="{{ route("resources.purchases.show", $purchase) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
