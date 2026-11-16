@extends("layouts.admin")
@section("title", __('app.edit_journal_entry'))
@section("page_title", __('app.edit_journal_entry') . " — " . $entry->entry_number)

@section("content")
@include("accounting.journal-entries._form")
@endsection
