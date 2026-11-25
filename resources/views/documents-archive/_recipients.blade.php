{{-- Recipients (admin, owner, accountant of the store) and the message of a sending. --}}
@php($chosen = $bag->any() ? array_map("intval", (array) old("recipients", [])) : [])
<div class="mb-3">
    <label class="form-label d-block">{{ __('app.doc_recipients') }} <span class="text-danger">*</span></label>
    <div class="doc-recipients @if($bag->has("recipients") || $bag->has("recipients.*")) is-invalid @endif">
        @foreach($recipients as $recipient)
            <label class="doc-recipient">
                <input type="checkbox" class="form-check-input" name="recipients[]" value="{{ $recipient->id }}" @checked(in_array($recipient->id, $chosen, true))>
                <span class="min-w-0">
                    <span class="d-block fw-medium text-truncate">{{ $recipient->name }}</span>
                    <span class="d-block small text-muted">{{ __('app.role_' . $recipient->role) }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @if($bag->has("recipients") || $bag->has("recipients.*"))<div class="invalid-feedback d-block">{{ $bag->first("recipients") ?: $bag->first("recipients.*") }}</div>@endif
</div>
<div>
    <label class="form-label" for="{{ $prefix }}Message">{{ __('app.doc_message') }}</label>
    <textarea name="message" id="{{ $prefix }}Message" rows="2" class="form-control @if($bag->has("message")) is-invalid @endif" maxlength="1000" placeholder="{{ __('app.doc_message_placeholder') }}">{{ $bag->any() ? old("message") : "" }}</textarea>
    @if($bag->has("message"))<div class="invalid-feedback">{{ $bag->first("message") }}</div>@endif
</div>
