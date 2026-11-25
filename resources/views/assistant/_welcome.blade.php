{{-- Start of a conversation: what the assistant does and example questions for the role. --}}
<div class="ai-welcome" id="aiWelcome">
    <div class="ai-welcome-icon"><i class="bi bi-stars"></i></div>
    <h5>{{ __('app.ai_welcome_title') }}</h5>
    <p class="text-muted">{{ __('app.ai_welcome_text') }}</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
        @foreach($suggestions as $s)
            <button type="button" class="btn btn-sm btn-outline-primary ai-suggestion" data-question="{{ $s }}">{{ $s }}</button>
        @endforeach
    </div>
</div>
