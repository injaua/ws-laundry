@extends("layouts.admin")
@section("title", __('app.new_vacation'))
@section("page_title", __('app.new_vacation'))

@section("content")
@include("vacations._form")
@endsection
