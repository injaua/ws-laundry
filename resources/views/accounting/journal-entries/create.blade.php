@extends("layouts.admin")
@section("title", __('app.new_journal_entry'))
@section("page_title", __('app.new_journal_entry'))

@section("content")
@include("accounting.journal-entries._form")
@endsection
