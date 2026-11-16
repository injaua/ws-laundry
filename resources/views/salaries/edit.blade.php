@extends("layouts.admin")
@section("title", __('app.edit_payslip'))
@section("page_title", __('app.edit_payslip'))

@section("content")
@include("salaries._form")
@endsection
