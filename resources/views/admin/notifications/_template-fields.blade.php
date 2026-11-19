@php($mine = (string) old("template_form") === (string) ($t?->id ?? "new"))
@php($val = fn ($field, $current) => $mine ? old($field, $current) : $current)
<div class="row g-2">
    <div class="col-md-4">
        <label class="form-label small">{{ __('app.name') }}</label>
        <input type="text" name="name" class="form-control form-control-sm @if($mine) @error("name") is-invalid @enderror @endif" value="{{ $val("name", $t?->name) }}" maxlength="100" required>
        @if($mine) @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
    </div>
    <div class="col-md-8 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active_{{ $t?->id ?? "new" }}" @checked($val("is_active", $t?->is_active ?? true))>
            <label class="form-check-label small" for="active_{{ $t?->id ?? "new" }}">{{ __('app.active') }}</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label small">{{ __('app.wa_message_text') }}</label>
        <textarea name="body" rows="2" class="form-control form-control-sm @if($mine) @error("body") is-invalid @enderror @endif" maxlength="1024" required>{{ $val("body", $t?->body) }}</textarea>
        @if($mine) @error("body") <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
    </div>
    <div class="col-md-4">
        <label class="form-label small">{{ __('app.wa_meta_template_name') }}</label>
        <input type="text" name="wa_template_name" class="form-control form-control-sm @if($mine) @error("wa_template_name") is-invalid @enderror @endif" value="{{ $val("wa_template_name", $t?->wa_template_name) }}" placeholder="pedido_pronto" pattern="[a-z0-9_]+">
        @if($mine) @error("wa_template_name") <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
    </div>
    <div class="col-md-3">
        <label class="form-label small">{{ __('app.wa_language') }}</label>
        <input type="text" name="wa_language" class="form-control form-control-sm @if($mine) @error("wa_language") is-invalid @enderror @endif" value="{{ $val("wa_language", $t?->wa_language) }}" placeholder="{{ config("whatsapp.template_language") }}">
        @if($mine) @error("wa_language") <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
    </div>
    <div class="col-md-5">
        <label class="form-label small">{{ __('app.wa_meta_params') }}</label>
        <input type="text" name="wa_params" class="form-control form-control-sm @if($mine) @error("wa_params") is-invalid @enderror @endif" value="{{ $val("wa_params", implode(", ", $t?->wa_params ?? [])) }}" placeholder="customer, order">
        @if($mine) @error("wa_params") <div class="invalid-feedback">{{ $message }}</div> @enderror @endif
    </div>
    <div class="col-12 form-text mt-0 mb-2">{{ __('app.wa_meta_help') }}</div>
</div>
