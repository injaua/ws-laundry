@extends("layouts.admin")
@section("title", __('app.new_employee'))
@section("page_title", __('app.new_employee'))

@section("content")
@include("employees._form")
@endsection
