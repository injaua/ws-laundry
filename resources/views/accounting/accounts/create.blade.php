@extends("layouts.admin")
@section("title", __('app.new_account'))
@section("page_title", __('app.new_account'))

@section("content")
@include("accounting.accounts._form")
@endsection
