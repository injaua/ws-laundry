@extends("layouts.admin")
@section("title", __('app.client_whatsapp'))
@section("page_title", __('app.whatsapp_panel'))

@push("styles")
    @vite("resources/css/pages/whatsapp.css")
@endpush

@php
    $initial = fn ($name) => mb_strtoupper(mb_substr(trim((string) $name) ?: "?", 0, 1));
    $tick = fn ($status) => match ($status) {
        "queued" => '<i class="bi bi-clock"></i>',
        "sent" => '<i class="bi bi-check2"></i>',
        "delivered" => '<i class="bi bi-check2-all"></i>',
        "read" => '<i class="bi bi-check2-all tick-read"></i>',
        "failed" => '<i class="bi bi-exclamation-circle text-danger"></i>',
        "manual" => '<i class="bi bi-phone"></i>',
        default => "",
    };
@endphp

@section("content")
@if($qrDisconnected)
    <div class="alert alert-danger py-2 small"><i class="bi bi-qr-code me-1"></i> {{ __('app.wa_web_disconnected_inbox') }}
        @if(auth()->user()->isAdmin() || auth()->user()->isOwner())<a href="{{ route("admin.notifications.index") }}">{{ __('app.wa_notifications') }}</a>@endif
    </div>
@endif
@unless($live)
    <div class="alert alert-warning py-2 small"><i class="bi bi-plug me-1"></i> {{ __('app.wa_log_mode') }}</div>
@endunless

<div class="wa-shell {{ $thread ? "has-thread" : "" }}">
    {{-- Conversations --}}
    <div class="card wa-list">
        <div class="card-header">
            <form action="{{ route("messages.clients.index") }}" method="GET">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="{{ __('app.wa_search_placeholder') }}">
                </div>
            </form>
        </div>
        <div class="list">
            @if($search !== "" && $customers->isNotEmpty())
                <div class="small text-muted px-3 pt-2">{{ __('app.customers') }}</div>
                @foreach($customers as $c)
                    @if($c->normalizedWhatsapp())
                    <a href="{{ route("messages.clients.thread", $c->normalizedWhatsapp()) }}" class="wa-item">
                        <span class="wa-avatar bg-secondary">{{ $initial($c->name) }}</span>
                        <span class="flex-grow-1 minw-0">
                            <span class="d-block fw-semibold text-truncate">{{ $c->name }}</span>
                            <span class="d-block small text-muted">+{{ $c->normalizedWhatsapp() }}</span>
                        </span>
                        <i class="bi bi-chat-text text-muted"></i>
                    </a>
                    @endif
                @endforeach
                <div class="small text-muted px-3 pt-2">{{ __('app.wa_conversations') }}</div>
            @endif

            @forelse($conversations as $conv)
                <a href="{{ route("messages.clients.thread", $conv->phone) }}" class="wa-item {{ $phone === $conv->phone ? "active" : "" }}">
                    <span class="wa-avatar">{{ $initial($conv->name ?? $conv->phone) }}</span>
                    <span class="flex-grow-1 minw-0">
                        <span class="d-flex justify-content-between">
                            <span class="fw-semibold text-truncate">{{ $conv->name ?? "+" . $conv->phone }}</span>
                            <span class="small text-muted ms-2 text-nowrap">{{ $conv->last->created_at->format("d/m H:i") }}</span>
                        </span>
                        <span class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted text-truncate">
                                @if(!$conv->last->isInbound()){!! $tick($conv->last->status) !!} @endif{{ $conv->last->body ?? "[" . $conv->last->type . "]" }}
                            </span>
                            @if($conv->unread > 0)
                                <span class="badge rounded-pill ms-2 bg-wa">{{ $conv->unread }}</span>
                            @endif
                        </span>
                    </span>
                </a>
            @empty
                <div class="text-center text-muted small p-4">{{ __('app.wa_no_conversations') }}</div>
            @endforelse
        </div>
    </div>

    {{-- Thread --}}
    <div class="card wa-thread">
        @if($thread)
            @php($displayName = $thread->customer?->name ?? $thread->profileName ?? "+" . $thread->phone)
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route("messages.clients.index") }}" class="btn btn-sm btn-outline-secondary d-md-none" aria-label="{{ __('app.back') }}"><i class="bi bi-arrow-left"></i></a>
                <span class="wa-avatar">{{ $initial($displayName) }}</span>
                <div class="flex-grow-1 minw-0">
                    <div class="fw-semibold text-truncate">
                        {{ $displayName }}
                        @if($thread->customer?->whatsapp_opt_out)
                            <span class="badge bg-secondary ms-1" title="{{ __('app.wa_opted_out_help') }}">{{ __('app.wa_opted_out') }}</span>
                        @endif
                    </div>
                    <div class="small text-muted">
                        +{{ $thread->phone }}
                        @if($thread->customer)
                            · <a href="{{ route("customers.show", $thread->customer) }}">{{ __('app.customer_detail') }}</a>
                        @else
                            · {{ __('app.wa_unknown_contact') }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="thread-body" id="waThread" data-automatic-label="{{ __('app.wa_automatic') }}" data-poll="{{ route("messages.clients.poll", $thread->phone) }}" data-last-id="{{ $thread->messages->max("id") ?? 0 }}">
                @forelse($thread->messages as $m)
                    <div class="wa-bubble {{ $m->isInbound() ? "wa-in" : "wa-out" }} {{ $m->status === "failed" ? "failed" : "" }}" data-id="{{ $m->id }}">
                        @if($m->isAutomatic())<div class="small text-muted mb-1"><i class="bi bi-robot"></i> {{ __('app.wa_automatic') }}</div>@endif
                        @if($m->type === "template" && $m->template)<div class="small text-muted mb-1"><i class="bi bi-file-earmark-text"></i> {{ $m->template->name }}</div>@endif
                        <div class="text">{{ $m->body ?? "[" . $m->type . "]" }}</div>
                        <div class="meta">
                            @if(!$m->isInbound() && $m->sender){{ $m->sender->name }} · @elseif($m->isFromPhone())<i class="bi bi-phone"></i> {{ __('app.wa_from_phone') }} · @endif{{ $m->created_at->format("d/m H:i") }}
                            @unless($m->isInbound())<span class="tick ms-1">{!! $tick($m->status) !!}</span>@endunless
                        </div>
                        @if($m->status === "failed" && $m->error)<div class="small text-danger mt-1">{{ $m->error }}</div>@endif
                    </div>
                @empty
                    <div class="text-center text-muted small py-5">{{ __('app.wa_no_messages_yet') }}</div>
                @endforelse
            </div>

            <div class="card-body border-top composer">
                @if($thread->canReply)
                    <form action="{{ route("messages.clients.reply", $thread->phone) }}" method="POST" class="d-flex gap-2 mb-2">
                        @csrf
                        <textarea name="body" class="form-control resize-v" rows="1" required maxlength="4096" placeholder="{{ __('app.type_message') }}">{{ old("body") }}</textarea>
                        <button type="submit" class="btn btn-success flex-shrink-0"><i class="bi bi-send"></i> {{ __('app.send') }}</button>
                    </form>
                    @if($thread->official && $thread->windowEndsAt)
                        <div class="small text-muted mb-2"><i class="bi bi-clock-history"></i> {{ __('app.wa_window_open_until', ['time' => $thread->windowEndsAt->format("d/m H:i")]) }}</div>
                    @endif
                @else
                    <div class="small text-muted mb-2"><i class="bi bi-lock"></i> {{ __('app.wa_window_closed_help') }}</div>
                @endif

                @if($thread->customer)
                    <form action="{{ route("messages.clients.send-template") }}" method="POST" class="row g-2 align-items-center">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $thread->customer->id }}">
                        <div class="col-sm-5">
                            <select name="template_id" class="form-select form-select-sm" required>
                                <option value="">{{ __('app.wa_choose_template') }}</option>
                                @foreach($templates as $t)
                                    <option value="{{ $t->id }}" @disabled($thread->official && !$thread->canReply && !$t->isApprovedOnWhatsapp())>
                                        {{ $t->name }}{{ $thread->official && $t->isApprovedOnWhatsapp() ? " ✓" : "" }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <select name="order_id" class="form-select form-select-sm">
                                @forelse($thread->orders as $o)
                                    <option value="{{ $o->id }}">{{ $o->order_number }}</option>
                                @empty
                                    <option value="">{{ __('app.wa_no_order') }}</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="col-sm-3 d-grid">
                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-text"></i> {{ __('app.wa_send_template') }}</button>
                        </div>
                        @if($thread->official)<div class="col-12 form-text mt-0">{{ __('app.wa_template_help') }}</div>@endif
                    </form>
                @endif
            </div>
        @else
            <div class="card-body d-flex align-items-center justify-content-center text-muted">
                <div class="text-center py-5">
                    <i class="bi bi-whatsapp text-2_5rem text-wa"></i>
                    <p class="mt-2 mb-0">{{ __('app.wa_select_conversation') }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push("scripts")
    @vite("resources/js/pages/whatsapp-inbox.js")
@endpush
