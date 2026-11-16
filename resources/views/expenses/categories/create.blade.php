@extends("layouts.admin")
@section("title", __('app.new_category'))
@section("page_title", __('app.new_category'))

@section("content")
@include("expenses.categories._form")
@endsection
