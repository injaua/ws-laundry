{{--
    Fields of the user form (create and edit). Expects: $user (new or existing), $roles, $stores, $current (store id).
    Every field has an .invalid-feedback the page script (user-form.js) also fills before submitting.
--}}
@php($isEdit = $user->exists)
@php($isSelf = $isEdit && $user->is(auth()->user()))

@if($errors->any())
    <div class="alert alert-danger" role="alert" id="userFormErrors" tabindex="-1">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> {{ __('app.user_form_errors') }}</div>
        <ul class="mb-0 small">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
{{-- Filled by the script when the browser-side check finds problems. --}}
<div class="alert alert-danger d-none" role="alert" id="userFormClientErrors" tabindex="-1">
    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> {{ __('app.user_form_errors') }}</div>
    <ul class="mb-0 small"></ul>
</div>

<div class="mb-3">
    <label class="form-label" for="userName">{{ __('app.name') }} <span class="text-danger">*</span></label>
    <input type="text" name="name" id="userName" value="{{ old("name", $user->name) }}" class="form-control @error("name") is-invalid @enderror" required minlength="2" maxlength="255" autocomplete="off" @if(!$isEdit) autofocus @endif>
    <div class="invalid-feedback" data-feedback="name">@foreach($errors->get("name") as $message){{ $message }} @endforeach</div>
</div>

<div class="mb-3">
    <label class="form-label" for="userEmail">{{ __('app.email') }} <span class="text-danger">*</span></label>
    <input type="email" name="email" id="userEmail" value="{{ old("email", $user->email) }}" class="form-control @error("email") is-invalid @enderror" required maxlength="255" autocomplete="off" autocapitalize="none" spellcheck="false">
    <div class="invalid-feedback" data-feedback="email">@foreach($errors->get("email") as $message){{ $message }} @endforeach</div>
    <div class="form-text">{{ __('app.user_email_help') }}</div>
</div>

<div class="mb-3">
    <label class="form-label" for="userRole">{{ __('app.role') }} <span class="text-danger">*</span></label>
    <select name="role" id="userRole" class="form-select @error("role") is-invalid @enderror" required @disabled($isSelf)>
        @unless($isEdit)
            <option value="" @selected(old("role") === null || old("role") === "")>{{ __('app.choose') }}</option>
        @endunless
        @foreach($roles as $role)
            <option value="{{ $role }}" @selected(old("role", $user->role) === $role)>{{ __('app.role_' . $role) }}</option>
        @endforeach
    </select>
    <div class="invalid-feedback" data-feedback="role">@foreach($errors->get("role") as $message){{ $message }} @endforeach</div>
    @if($isSelf)
        <div class="form-text">{{ __('app.cannot_change_own_role') }}</div>
    @endif
</div>

{{-- Admins work in every store: the store applies to the other roles only. --}}
<div data-show-if="role!=admin">
    @include("users._store")
</div>

<div class="mb-3">
    <label class="form-label" for="userPassword">{{ $isEdit ? __('app.new_password') : __('app.password') }} @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
    <div class="input-group has-validation">
        <input type="password" name="password" id="userPassword" class="form-control @error("password") is-invalid @enderror" @unless($isEdit) required @endunless autocomplete="new-password" @if($isEdit) placeholder="{{ __('app.password_keep_blank') }}" @endif>
        <button class="btn btn-outline-secondary" type="button" data-toggle-password="userPassword" aria-label="{{ __('app.show_password') }}" aria-pressed="false"><i class="bi bi-eye"></i></button>
        <div class="invalid-feedback" data-feedback="password">@foreach($errors->get("password") as $message)<div>{{ $message }}</div>@endforeach</div>
    </div>
    <ul class="list-unstyled small mt-2 mb-0 password-rules" id="passwordRules">
        <li data-rule="length"><i class="bi bi-circle"></i> {{ __('app.pw_rule_length') }}</li>
        <li data-rule="case"><i class="bi bi-circle"></i> {{ __('app.pw_rule_case') }}</li>
        <li data-rule="number"><i class="bi bi-circle"></i> {{ __('app.pw_rule_number') }}</li>
    </ul>
</div>

<div class="mb-3">
    <label class="form-label" for="userPasswordConfirm">{{ __('app.confirm_password') }} @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
    <div class="input-group has-validation">
        <input type="password" name="password_confirmation" id="userPasswordConfirm" class="form-control" @unless($isEdit) required @endunless autocomplete="new-password">
        <button class="btn btn-outline-secondary" type="button" data-toggle-password="userPasswordConfirm" aria-label="{{ __('app.show_password') }}" aria-pressed="false"><i class="bi bi-eye"></i></button>
        <div class="invalid-feedback" data-feedback="password_confirmation"></div>
        <div class="valid-feedback">{{ __('app.pw_match') }}</div>
    </div>
</div>

@push("scripts")
<x-page-data name="user-form" :data="[
    'isEdit' => $isEdit,
    'messages' => [
        'required' => __('app.field_required'),
        'name' => __('app.user_name_short'),
        'email' => __('app.email_invalid'),
        'role' => __('app.role_required'),
        'password' => __('app.pw_rules_not_met'),
        'mismatch' => __('app.pw_mismatch'),
        'saving' => __('app.saving'),
    ],
]"/>
@vite("resources/js/pages/user-form.js")
@endpush
