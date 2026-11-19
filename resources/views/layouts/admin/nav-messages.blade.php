{{-- Sidebar · Internal chat and customer WhatsApp. --}}
<div class="nav-section mt-3">{{ __('app.messages') }}</div>
@php($messagesActive = request()->routeIs("messages.*"))
<a class="nav-link d-flex justify-content-between align-items-center {{ $messagesActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseMessages" role="button" aria-expanded="{{ $messagesActive ? "true" : "false" }}" aria-controls="collapseMessages">
    <span><i class="bi bi-chat-dots"></i> {{ __('app.messages') }}</span>
    <span>
        <span class="badge rounded-pill bg-primary {{ $navBadges['chat_unread'] ? "" : "d-none" }}" id="chatUnreadBadge" data-url="{{ route("messages.unread") }}">{{ $navBadges['chat_unread'] }}</span>
        <i class="bi bi-chevron-down chev"></i>
    </span>
</a>
<div class="collapse {{ $messagesActive ? "show" : "" }}" id="collapseMessages">
    <a href="{{ route("messages.index") }}" class="nav-link ms-3 {{ request()->routeIs("messages.index") || request()->routeIs("messages.conversations.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-people"></i> {{ __('app.internal_chat') }}
    </a>
    @if(auth()->user()->can("answer-customers"))
    <a href="{{ route("messages.clients.index") }}" class="nav-link ms-3 {{ request()->routeIs("messages.clients.*") ? "active" : "" }} nav-sub">
        <span><i class="bi bi-whatsapp"></i> {{ __('app.client_whatsapp') }}</span>
        @if($navBadges['whatsapp_unread'] > 0)<span class="badge rounded-pill ms-1 nav-badge nav-badge-wa">{{ $navBadges['whatsapp_unread'] }}</span>@endif
    </a>
    @endif
</div>
