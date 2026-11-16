@extends("layouts.admin")
@section("title", __('app.edit_vacation'))
@section("page_title", __('app.edit_vacation'))

@section("content")
@include("vacations._form")
@endsection
