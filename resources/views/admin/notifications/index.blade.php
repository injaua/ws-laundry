@extends("layouts.admin")
@section("title", __('app.wa_notifications'))
@section("page_title", __('app.wa_notifications'))

@section("content")
{{-- Channels --}}
@php($channelLabel = fn ($c) => match ($c) { "cloud" => __('app.wa_channel_cloud'), "web" => __('app.wa_channel_web'), "log" => __('app.wa_mode_log'), default => __('app.wa_channel_off') })
{{-- The company number or a store's own (admin); the owner configures their store's own number. --}}
@include("admin.partials.settings-level", ["bar" => $levelBar, "release" => "admin.notifications.channels.release"])
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-whatsapp me-1 text-wa"></i> {{ __('app.wa_connection') }}
        <span class="badge bg-light text-dark border ms-1">{{ $levelBar['level'] === null ? __('app.settings_level_company') : __('app.settings_level_store', ['name' => $levelBar['store']?->name]) }}</span>
    </div>
    <div class="card-body small">
        @if(auth()->user()->hasRole(["admin", "owner"]))
        <form action="{{ route("admin.notifications.channels") }}" method="POST" class="mb-3">
            @csrf
            <input type="hidden" name="level" value="{{ $levelParam }}">
            <div class="fw-semibold mb-2">{{ __('app.wa_conversations_channel') }}</div>
            <div class="row g-2 mb-3">
                @foreach($drivers as $d)
                <div class="col-md-4">
                    <label class="border rounded p-2 h-100 d-block cursor-pointer {{ $conversationChannel === $d ? "border-success bg-success-subtle" : "" }}">
                        <input type="radio" name="driver" value="{{ $d }}" class="form-check-input me-1" @checked(old("driver", $conversationChannel) === $d)>
                        <span class="fw-semibold">{{ $channelLabel($d) }}</span>
                        <span class="d-block text-muted mt-1">{{ __('app.wa_driver_' . $d . '_help') }}</span>
                    </label>
                </div>
                @endforeach
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="notifications_driver" class="form-label fw-semibold mb-1">{{ __('app.wa_notifications_channel') }}</label>
                    <select name="notifications_driver" id="notifications_driver" class="form-select form-select-sm">
                        @foreach(["" => __('app.wa_notify_same'), "cloud" => __('app.wa_channel_cloud'), "log" => __('app.wa_mode_log'), "off" => __('app.wa_channel_off')] as $value => $label)
                            <option value="{{ $value }}" @selected(old("notifications_driver", $notificationsSetting) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 text-md-end">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.wa_save_channels') }}</button>
                </div>
            </div>
            <div class="mt-2 {{ $notificationChannel ? "text-muted" : "text-danger" }}">
                {{ __('app.wa_notifications_now') }}: <span class="fw-semibold">{{ $channelLabel($notificationChannel) }}</span>
                @unless($notificationChannel) — {{ __('app.wa_notifications_off_help') }}@endunless
            </div>

            {{-- Official API number of this level (empty at company level = the .env values). --}}
            <details class="border rounded mt-3" @if($errors->has('cloud.*')) open @endif>
                <summary class="px-3 py-2 cursor-pointer fw-semibold"><i class="bi bi-patch-check"></i> {{ __('app.wa_cloud_credentials') }}</summary>
                <div class="p-3 border-top">
                    <p class="text-muted mb-2">{{ $levelParam === 'company' ? __('app.wa_cloud_credentials_company_help') : __('app.wa_cloud_credentials_store_help') }}</p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label mb-0" for="cloud_phone_number_id">Phone number ID</label>
                            <input type="text" name="cloud[phone_number_id]" id="cloud_phone_number_id" inputmode="numeric" autocomplete="off" class="form-control form-control-sm @error('cloud.phone_number_id') is-invalid @enderror" value="{{ old('cloud.phone_number_id', $cloud['phone_number_id']) }}" placeholder="{{ $cloud['phone_number_id_hint'] }}">
                            @error('cloud.phone_number_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-0" for="cloud_verify_token">Verify token</label>
                            <input type="text" name="cloud[verify_token]" id="cloud_verify_token" autocomplete="off" class="form-control form-control-sm @error('cloud.verify_token') is-invalid @enderror" value="{{ old('cloud.verify_token', $cloud['verify_token']) }}" placeholder="{{ $cloud['verify_token_hint'] }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-0" for="cloud_access_token">Access token</label>
                            <input type="password" name="cloud[access_token]" id="cloud_access_token" autocomplete="off" class="form-control form-control-sm" placeholder="{{ $cloud['has_access_token'] ? __('app.mm_secret_kept') : '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-0" for="cloud_app_secret">App secret</label>
                            <input type="password" name="cloud[app_secret]" id="cloud_app_secret" autocomplete="off" class="form-control form-control-sm" placeholder="{{ $cloud['has_app_secret'] ? __('app.mm_secret_kept') : '' }}">
                        </div>
                    </div>
                    <div class="text-end mt-2">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.wa_save_channels') }}</button>
                    </div>
                </div>
            </details>
        </form>
        @else
            <p class="mb-2">{{ __('app.wa_notifications_now') }}: <span class="fw-semibold">{{ $channelLabel($notificationChannel) }}</span></p>
            <p class="text-muted mb-2"><i class="bi bi-lock"></i> {{ __('app.wa_channel_admin_only') }}</p>
        @endif

        @if($conversationChannel === "log")
            <p class="mb-2"><span class="badge bg-warning text-dark">{{ __('app.wa_mode_log') }}</span> {{ __('app.wa_log_mode') }}</p>
        @endif

        @if($conversationChannel === "web" && auth()->user()->hasRole(["admin", "owner"]))
            <div class="border rounded p-3 mb-3" id="waQr" data-status="{{ route("admin.notifications.web-status", ["level" => $levelParam]) }}">
                <div class="fw-semibold mb-1"><i class="bi bi-qr-code"></i> {{ __('app.wa_channel_web') }}</div>
                @unless($webTokenOk)
                    <div class="alert alert-danger py-2 mb-2">{{ __('app.wa_web_token_missing') }}</div>
                @endunless
                <div class="d-flex flex-wrap gap-3 align-items-start">
                    <img id="waQrImg" alt="QR" class="border rounded d-none" width="220" height="220">
                    <div class="mw-520px">
                        <div class="mb-2"><span id="waQrState" class="badge bg-secondary">…</span> <span id="waQrMe" class="fw-semibold"></span></div>
                        <p id="waQrHelp" class="text-muted mb-2"></p>
                        <form action="{{ route("admin.notifications.web-start") }}" method="POST" id="waQrStart" class="d-none mb-2">
                            @csrf
                            <input type="hidden" name="level" value="{{ $levelParam }}">
                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-play-fill"></i> {{ __('app.wa_web_start') }}</button>
                        </form>
                        <pre id="waQrLog" class="d-none small bg-body-tertiary border rounded p-2 mb-2 mh-240px overflow-auto pre-wrap"></pre>
                        <p class="text-muted mb-2">{{ __('app.wa_web_start_help') }}</p>
                        <form action="{{ route("admin.notifications.web-logout") }}" method="POST" id="waQrLogout" class="d-none" data-confirm="{{ __('app.wa_web_logout_confirm') }}">
                            @csrf
                            <input type="hidden" name="level" value="{{ $levelParam }}">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> {{ __('app.wa_web_logout') }}</button>
                        </form>
                    </div>
                </div>
                <div class="alert alert-warning py-2 mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> {{ __('app.wa_web_risk') }}</div>
            </div>
        @endif

        @if($usesCloud)
            <p class="mb-2">
                <span class="badge bg-{{ $configured ? "success" : "danger" }}">{{ $configured ? __('app.wa_cloud_connected') : __('app.wa_cloud_missing_config') }}</span>
                @unless($webhookSigned)
                    <span class="badge bg-danger ms-1">{{ __('app.wa_webhook_unsigned') }}</span>
                @endunless
            </p>
            <div>{{ __('app.wa_webhook_url') }}: <code>{{ $webhookUrl }}</code></div>
        @endif
        <div class="text-muted mt-1">{{ __('app.wa_setup_help') }}</div>
    </div>
</div>

{{-- Events --}}
<form action="{{ route("admin.notifications.update") }}" method="POST" class="card mb-3">
    @csrf
    <div class="card-header"><i class="bi bi-bell me-1"></i> {{ __('app.wa_automatic_notifications') }} <span class="badge bg-light text-dark border ms-1">{{ __('app.settings_level_store', ['name' => $currentStore?->name]) }}</span></div>
    <div class="card-body p-0">
        <p class="small text-muted px-3 pt-2 mb-0">{{ __('app.wa_per_store_help', ['name' => $currentStore?->name]) }}</p>
        <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th class="w-70px">{{ __('app.wa_active') }}</th>
                    <th>{{ __('app.wa_event') }}</th>
                    <th class="minw-200px">{{ __('app.template') }}</th>
                    <th class="w-140px">{{ __('app.wa_days') }}</th>
                    <th class="text-end w-120px">{{ __('app.wa_last_30_days') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($events as $e)
                <tr>
                    <td>
                        <div class="form-check form-switch m-0">
                            <input type="hidden" name="events[{{ $e->key }}][enabled]" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="events[{{ $e->key }}][enabled]" value="1" id="ev_{{ $e->key }}" @checked(old("events.{$e->key}.enabled", $e->enabled))>
                        </div>
                    </td>
                    <td>
                        <label for="ev_{{ $e->key }}" class="fw-semibold">{{ __('app.wa_event_' . $e->key) }}</label>
                        <div class="small text-muted">{{ __('app.wa_event_' . $e->key . '_help') }}</div>
                    </td>
                    <td>
                        <select name="events[{{ $e->key }}][template_id]" class="form-select form-select-sm @error("events.{$e->key}.template_id") is-invalid @enderror">
                            <option value="">—</option>
                            @foreach($templates->where("is_active", true) as $t)
                                <option value="{{ $t->id }}" @selected((int) old("events.{$e->key}.template_id", $e->template_id) === $t->id)>{{ $t->name }}{{ $t->isApprovedOnWhatsapp() ? " ✓" : "" }}</option>
                            @endforeach
                        </select>
                        @error("events.{$e->key}.template_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </td>
                    <td>
                        @if($e->days !== null)
                            <div class="input-group input-group-sm">
                                <input type="number" name="events[{{ $e->key }}][days]" class="form-control" min="1" max="365" value="{{ old("events.{$e->key}.days", $e->days) }}">
                                <span class="input-group-text">{{ __('app.wa_days_unit') }}</span>
                            </div>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end small">
                        {{ $e->sent_30d }}
                        @if($e->failed_30d)<span class="text-danger ms-1" title="{{ __('app.wa_failed') }}">({{ $e->failed_30d }} <i class="bi bi-exclamation-circle"></i>)</span>@endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center bg-transparent">
        <span class="small text-muted me-3">{{ __('app.wa_schedule_help') }}</span>
        <button type="submit" class="btn btn-primary flex-shrink-0 text-nowrap"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
    </div>
</form>

@if($recentFailures->isNotEmpty())
<div class="card mb-3 border-danger">
    <div class="card-header text-danger"><i class="bi bi-exclamation-triangle me-1"></i> {{ __('app.wa_recent_failures') }}</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm mb-0">
            <tbody>
                @foreach($recentFailures as $f)
                <tr>
                    <td class="small text-nowrap">{{ $f->created_at->format("d/m H:i") }}</td>
                    <td class="small">{{ $f->customer?->name ?? "+" . $f->phone }}</td>
                    <td class="small text-danger">{{ $f->error }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif

{{-- Templates --}}
<div class="card">
    <div class="card-header"><i class="bi bi-file-earmark-text me-1"></i> {{ __('app.wa_templates') }} <span class="badge bg-light text-dark border ms-1">{{ __('app.settings_level_store', ['name' => $currentStore?->name]) }}</span></div>
    <div class="card-body">
        <p class="small text-muted">{{ __('app.wa_per_store_help', ['name' => $currentStore?->name]) }} {{ __('app.wa_templates_help') }} <code>{{ "{" . implode("} {", \App\Models\MessageTemplate::PLACEHOLDERS) . "}" }}</code></p>

        @foreach($templates as $t)
        <details class="border rounded mb-2" @if($errors->any() && (int) old("template_form") === $t->id) open @endif>
            <summary class="px-3 py-2 d-flex justify-content-between align-items-center cursor-pointer">
                <span>
                    <span class="fw-semibold">{{ $t->name }}</span>
                    @unless($t->is_active)<span class="badge bg-secondary ms-1">{{ __('app.inactive') }}</span>@endunless
                </span>
                <span class="small {{ $t->isApprovedOnWhatsapp() ? "text-success" : "text-muted" }}">
                    {{ $t->isApprovedOnWhatsapp() ? "✓ " . $t->wa_template_name : __('app.wa_not_linked') }}
                </span>
            </summary>
            <form action="{{ route("admin.notifications.templates.update", $t) }}" method="POST" class="p-3 border-top">
                @csrf @method("PUT")
                <input type="hidden" name="template_form" value="{{ $t->id }}">
                @include("admin.notifications._template-fields", ["t" => $t])
                <button type="submit" class="btn btn-sm btn-primary">{{ __('app.save') }}</button>
            </form>
        </details>
        @endforeach

        <details class="border rounded mt-3" @if($errors->any() && old("template_form") === "new") open @endif>
            <summary class="px-3 py-2 cursor-pointer"><i class="bi bi-plus-lg"></i> {{ __('app.wa_new_template') }}</summary>
            <form action="{{ route("admin.notifications.templates.store") }}" method="POST" class="p-3 border-top">
                @csrf
                <input type="hidden" name="template_form" value="new">
                @include("admin.notifications._template-fields", ["t" => null])
                <button type="submit" class="btn btn-sm btn-primary">{{ __('app.save') }}</button>
            </form>
        </details>
    </div>
</div>
@endsection

@if($conversationChannel === "web")
@php($qrLabels = [
        "open" => [__('app.wa_web_state_open'), "bg-success", __('app.wa_web_help_open')],
        "qr" => [__('app.wa_web_state_qr'), "bg-warning text-dark", __('app.wa_web_help_qr')],
        "starting" => [__('app.wa_web_state_starting'), "bg-secondary", ""],
        "reconnecting" => [__('app.wa_web_state_starting'), "bg-secondary", ""],
        "logged_out" => [__('app.wa_web_state_starting'), "bg-secondary", ""],
        "booting" => [__('app.wa_web_state_booting'), "bg-info text-dark", __('app.wa_web_help_booting')],
        "offline" => [__('app.wa_web_state_offline'), "bg-danger", __('app.wa_web_help_offline')],
        "unauthorized" => [__('app.wa_web_state_offline'), "bg-danger", __('app.wa_web_token_missing')],
    ])
@push("scripts")
    <x-page-data name="whatsapp-qr" :data="['labels' => $qrLabels]"/>
    @vite("resources/js/pages/whatsapp-qr.js")
@endpush
@endif
