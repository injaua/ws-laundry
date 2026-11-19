@extends("layouts.admin")
@section("title", __('app.internal_chat'))
@section("page_title", __('app.internal_chat'))

@push("styles")
    @vite("resources/css/pages/chat.css")
@endpush

@section("content")
<div class="chat-shell {{ $conversation ? "has-thread" : "" }}">
    {{-- Conversation list --}}
    <div class="card chat-list">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-chat-square-dots me-1"></i> {{ __('app.messages') }}</span>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newConvModal">
                <i class="bi bi-plus-lg"></i> {{ __('app.new_conversation') }}
            </button>
        </div>
        <div class="list">
            @forelse($conversations as $c)
                @php($unread = $c->unreadCountFor(auth()->user()))
                <a href="{{ route("messages.index", ["conversation" => $c->id]) }}"
                    class="conv-item {{ $conversation && $conversation->id === $c->id ? "active" : "" }}">
                    <div class="d-flex gap-2 align-items-center">
                        <span class="avatar-init">{{ mb_substr($c->displayName(auth()->user()), 0, 1) }}</span>
                        <div class="flex-grow-1 minw-0">
                            <div class="d-flex justify-content-between">
                                <strong class="text-truncate">
                                    @if($c->isDirect())
                                        {{ $c->displayName(auth()->user()) }}
                                    @else
                                        <i class="bi bi-people-fill me-1 text-muted"></i>{{ $c->displayName(auth()->user()) }}
                                    @endif
                                </strong>
                                @if($unread > 0)
                                    <span class="badge rounded-pill bg-primary">{{ $unread }}</span>
                                @endif
                            </div>
                            <div class="small text-muted text-truncate">
                                {{ $c->lastMessage ? $c->lastMessage->author?->name . ": " . \Illuminate\Support\Str::limit($c->lastMessage->body, 40) : "—" }}
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-4 text-center text-muted small">{{ __('app.chat_empty') }}</div>
            @endforelse
        </div>
    </div>

    {{-- Thread --}}
    <div class="card chat-thread">
        @if($conversation)
            <div class="card-header">
                <a href="{{ route("messages.index") }}" class="btn btn-sm btn-outline-secondary me-2 d-md-none" aria-label="{{ __('app.back') }}"><i class="bi bi-arrow-left"></i></a>
                <i class="bi {{ $conversation->isDirect() ? "bi-person" : "bi-people" }} me-1"></i>
                <strong>{{ $conversation->displayName(auth()->user()) }}</strong>
                <span class="small text-muted ms-2">{{ $conversation->participants->pluck("name")->join(", ") }}</span>
            </div>
            <div class="thread-body" id="threadBody" data-poll-url="{{ url("messages/conversations/" . $conversation->id . "/poll") }}" data-last-id="{{ $conversation->messages->max("id") ?? 0 }}">
                @forelse($conversation->messages as $m)
                    <div class="bubble {{ $m->user_id === auth()->id() ? "bubble-mine" : "bubble-other" }}">
                        @if(!$conversation->isDirect() && $m->user_id !== auth()->id())
                            <div class="fw-semibold small">{{ $m->author?->name }}</div>
                        @endif
                        <div>{{ $m->body }}</div>
                        <div class="meta">{{ $m->created_at->format(datetime_fmt()) }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">{{ __('app.no_messages_yet') }}</div>
                @endforelse
            </div>
            <div class="card-body border-top">
                <form action="{{ route("messages.messages.store", $conversation) }}" method="POST" class="d-flex gap-2">
                    @csrf
                    <textarea name="body" class="form-control resize-v" rows="1" required maxlength="2000"
                        placeholder="{{ __('app.type_message') }}"></textarea>
                    <button type="submit" class="btn btn-primary flex-shrink-0"><i class="bi bi-send"></i> {{ __('app.send') }}</button>
                </form>
            </div>
        @else
            <div class="card-body d-flex align-items-center justify-content-center text-muted">
                <div class="text-center py-5">
                    <i class="bi bi-chat-dots text-2_5rem"></i>
                    <p class="mt-2 mb-0">{{ __('app.select_conversation') }}</p>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- New conversation modal --}}
<div class="modal fade" id="newConvModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route("messages.conversations.store") }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('app.new_conversation') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if($errors->any())
                    <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label">{{ __('app.title') }}</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="type" id="typeDirect" value="direct" checked>
                        <label class="btn btn-outline-primary btn-sm" for="typeDirect">{{ __('app.direct') }}</label>
                        <input type="radio" class="btn-check" name="type" id="typeMeeting" value="meeting">
                        <label class="btn btn-outline-primary btn-sm" for="typeMeeting">{{ __('app.meeting') }}</label>
                    </div>
                </div>
                <div class="mb-3 d-none" id="meetingTitleWrap">
                    <label class="form-label" for="convTitle">{{ __('app.title') }}</label>
                    <input type="text" name="title" id="convTitle" class="form-control" maxlength="150">
                </div>
                <div class="mb-1 fw-semibold small">{{ __('app.participants') }}</div>
                <div class="border rounded p-2 mh-240px overflow-y-auto">
                    @foreach($pickerUsers as $u)
                        <label class="d-flex align-items-center gap-2 py-1 px-1 user-list-item">
                            <input type="checkbox" class="form-check-input m-0 participant-check" name="user_ids[]" value="{{ $u->id }}">
                            <span class="avatar-init avatar-26">{{ mb_substr($u->name, 0, 1) }}</span>
                            <span>{{ $u->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-chat-left-text me-1"></i>{{ __('app.new_conversation') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push("scripts")
    @vite("resources/js/pages/chat.js")
@endpush
