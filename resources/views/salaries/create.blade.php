@extends("layouts.admin")
@section("title", __('app.new_payslip'))
@section("page_title", __('app.new_payslip'))

@section("content")
@include("salaries._form")
@endsection
