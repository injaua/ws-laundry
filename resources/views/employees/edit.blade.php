@extends("layouts.admin")
@section("title", __('app.edit_employee'))
@section("page_title", __('app.edit_employee'))

@section("content")
@include("employees._form")
@endsection
