@extends("layouts.admin")
@section("title", __('app.edit_category'))
@section("page_title", __('app.edit_category'))

@section("content")
@include("expenses.categories._form")
@endsection
