@extends("layouts.admin")
@section("title", __('app.new_expense'))
@section("page_title", __('app.new_expense'))

@section("content")
@include("expenses._form")
@endsection
