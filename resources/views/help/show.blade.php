@extends("layouts.admin")

@section("title", $topic["title"] . " · " . __('app.help'))
@section("page_title", __('app.help'))

@push("styles")
    @vite("resources/css/pages/help.css")
@endpush

@section("content")
<div class="row g-3">
    <div class="col-lg-3 d-none d-lg-block">
        @include("help._topics")
    </div>
    <div class="col-lg-9">
        <a href="{{ route("help.index") }}" class="btn btn-sm btn-outline-secondary mb-3 d-lg-none"><i class="bi bi-arrow-left"></i> {{ __('app.help_home') }}</a>
        <article class="card">
            <div class="card-body help-content">
                <h3 class="help-title"><i class="bi {{ $topic["icon"] }} me-2"></i>{{ $topic["title"] }}</h3>
                {!! $content !!}
            </div>
        </article>
    </div>
</div>
@endsection
