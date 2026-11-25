@extends("layouts.admin")
@section("title", __('app.ai_assistant'))
@section("page_title", __('app.ai_assistant'))

@push("styles")
    @vite("resources/css/pages/assistant.css")
@endpush

@section("content")
<div class="row g-3 ai-page">
    {{-- Conversations of the user --}}
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">{{ __('app.ai_conversations') }}</span>
                <a href="{{ route('assistant.index') }}" class="btn btn-sm btn-primary" id="aiNew"><i class="bi bi-plus-lg"></i> {{ __('app.ai_new_conversation') }}</a>
            </div>
            <div class="list-group list-group-flush ai-conversations">
                @forelse($conversations as $c)
                    <div class="list-group-item d-flex align-items-center gap-2 {{ $current?->id === $c->id ? 'active' : '' }}">
                        <a href="{{ route('assistant.index', ['c' => $c->id]) }}" class="flex-grow-1 text-truncate text-reset text-decoration-none" title="{{ $c->title }}">
                            <div class="text-truncate small fw-semibold">{{ $c->title }}</div>
                            <div class="ai-muted">{{ $c->updated_at?->diffForHumans() }}</div>
                        </a>
                        <form method="POST" action="{{ route('assistant.destroy', $c) }}" data-confirm="{{ __('app.ai_delete_conversation_confirm') }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-link text-reset p-0" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                @empty
                    <div class="list-group-item text-muted small">{{ __('app.ai_no_conversations') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Chat --}}
    <div class="col-lg-9">
        <div class="card ai-chat">
            <div class="card-body ai-messages" id="aiMessages">
                @if($current)
                    @foreach($current->messages as $m)
                        <div class="ai-msg ai-msg-{{ $m->role }} {{ $m->failed ? 'ai-msg-failed' : '' }}">
                            <div class="ai-bubble">{!! $m->html() !!}</div>
                        </div>
                    @endforeach
                @else
                    @include("assistant._welcome")
                @endif
            </div>
            {{-- Shown again when a new conversation starts without leaving the page. --}}
            <template id="aiWelcomeTemplate">@include("assistant._welcome")</template>

            <div class="card-footer">
                @unless($ready)
                    <div class="alert alert-warning small mb-2" id="aiNotReady">
                        <i class="bi bi-exclamation-triangle"></i> {{ __('app.ai_not_configured') }}
                        @if($missing)
                            <span class="d-block mt-1">{{ __('app.ai_missing') }}: <strong>{{ collect($missing)->map(fn ($m) => __('app.ai_missing_' . $m))->implode(', ') }}</strong>.</span>
                        @endif
                        @if($canConfigure) <a href="{{ route('admin.ai.edit') }}" class="alert-link">{{ __('app.ai_configure_now') }}</a>
                        @else <span class="d-block">{{ __('app.ai_ask_manager') }}</span> @endif
                    </div>
                @endunless
                <form id="aiForm" method="post" action="{{ route('assistant.ask') }}" data-new-url="{{ route('assistant.index') }}" data-ready="{{ $ready ? 1 : 0 }}" data-conversation="{{ $current?->id }}" data-thinking="{{ __('app.ai_thinking') }}" data-error="{{ __('app.ai_error_generic') }}">
                    <div class="input-group">
                        <textarea name="question" id="aiQuestion" class="form-control" rows="2" maxlength="2000" placeholder="{{ __('app.ai_placeholder') }}" @disabled(!$ready) required>{{ $prefill }}</textarea>
                        <button class="btn btn-primary" id="aiSend" @disabled(!$ready)><i class="bi bi-send"></i></button>
                    </div>
                </form>
                <div class="d-flex justify-content-between ai-muted mt-1">
                    <span><i class="bi bi-shield-lock"></i> {{ __('app.ai_scope_note') }}</span>
                    @if($questionsLeft !== null)<span>{{ __('app.ai_questions_left', ['count' => $questionsLeft]) }}</span>@endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push("scripts")
    @vite("resources/js/pages/assistant.js")
@endpush
