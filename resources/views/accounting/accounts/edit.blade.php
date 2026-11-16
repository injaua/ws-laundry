@extends("layouts.admin")
@section("title", __('app.edit_account'))
@section("page_title", __('app.edit_account'))

@section("content")
@include("accounting.accounts._form")
@endsection
