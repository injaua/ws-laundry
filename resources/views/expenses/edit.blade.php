@extends("layouts.admin")
@section("title", __('app.edit_expense'))
@section("page_title", __('app.edit_expense'))

@section("content")
@include("expenses._form")
@endsection
