@extends("layouts.admin")

@section("title", __('app.help'))
@section("page_title", __('app.help'))

@push("styles")
    @vite("resources/css/pages/help.css")
@endpush

@section("content")
<div class="row g-3">
    <div class="col-lg-3">
        @include("help._topics")
    </div>
    <div class="col-lg-9">
        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-1">{{ __('app.help_welcome', ["name" => auth()->user()->name]) }}</h4>
                <p class="text-muted mb-0">{{ __('app.help_intro', ["role" => __('app.role_' . auth()->user()->role)]) }}</p>
            </div>
        </div>

        @if($roleSummary !== "")
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-person-check me-1"></i> {{ __('app.help_your_role') }}</div>
            <div class="card-body help-content">{!! $roleSummary !!}</div>
        </div>
        @endif

        @foreach($sections as $section => $topics)
            <h6 class="help-section-title">{{ __('app.help_s_' . $section) }}</h6>
            <div class="row g-2 mb-3">
                @foreach($topics as $item)
                <div class="col-sm-6 col-xl-4">
                    <a href="{{ route("help.show", $item["key"]) }}" class="help-tile card h-100">
                        <div class="card-body d-flex align-items-center gap-2">
                            <i class="bi {{ $item["icon"] }} help-tile-icon"></i>
                            <span>{{ $item["title"] }}</span>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
@endsection
