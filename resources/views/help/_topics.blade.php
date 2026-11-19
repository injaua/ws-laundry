{{-- Help · topic list for the reader's role. --}}
<nav class="help-nav card">
    <div class="card-body p-2">
        <a href="{{ route("help.index") }}" class="help-nav-link {{ request()->routeIs("help.index") ? "active" : "" }}">
            <i class="bi bi-house"></i> {{ __('app.help_home') }}
        </a>
        @foreach($sections as $section => $topics)
            <div class="help-nav-section">{{ __('app.help_s_' . $section) }}</div>
            @foreach($topics as $item)
                <a href="{{ route("help.show", $item["key"]) }}" class="help-nav-link {{ ($topic["key"] ?? null) === $item["key"] ? "active" : "" }}">
                    <i class="bi {{ $item["icon"] }}"></i> {{ $item["title"] }}
                </a>
            @endforeach
        @endforeach
    </div>
</nav>
