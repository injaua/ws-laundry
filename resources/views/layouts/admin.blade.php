<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield("title", "Laundry App") - {{ config("app.name") }}</title>
    <link rel="icon" href="{{ \App\Support\SystemSettings::faviconUrl() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap{{ is_rtl() ? '.rtl' : '' }}.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(["resources/css/admin.css", "resources/js/admin.js"])
    @stack("styles")
    <x-page-data name="app" :data="[
        'money' => \App\Support\SystemSettings::forJs(),
        'maxUploadBytes' => \Symfony\Component\HttpFoundation\File\UploadedFile::getMaxFilesize(),
        'texts' => ['loading' => __('app.loading'), 'fileTooLarge' => __('app.file_too_large')],
    ]"/>
</head>
<body>
    @include("layouts.admin.sidebar")
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-content">
        <div class="topbar">
            <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle" aria-controls="sidebar" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <h5 class="topbar-title" title="@yield("page_title", "Dashboard")">@yield("page_title", "Dashboard")</h5>
            <div class="topbar-actions d-flex align-items-center gap-2">
                @include("layouts.admin.store-switcher")
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-translate"></i>
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach(\App\Support\Languages::active() as $lang)
                            <li><a class="dropdown-item {{ app()->getLocale() == $lang->code ? 'active' : '' }}" href="{{ route('language.switch', $lang->code) }}">{{ $lang->label() }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <button id="darkModeToggle" class="btn btn-sm btn-outline-secondary" title="{{ __('app.toggle_dark_mode') }}">
                    <i id="darkModeIcon" class="bi bi-moon-stars"></i>
                </button>
                @include("layouts.admin.user-menu")
            </div>
        </div>
        <div class="content-wrapper">
            @if(session("success"))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-1"></i> {{ session("success") }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session("warning"))
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="bi bi-exclamation-circle me-1"></i> {{ session("warning") }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session("error"))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-1"></i> {{ session("error") }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @yield("content")
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    @stack("scripts")
</body>
</html>