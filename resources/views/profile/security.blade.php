@extends("layouts.admin")
@section("title", __('app.account_security'))
@section("page_title", __('app.account_security'))

@section("content")
<div class="row g-3">
    <div class="col-lg-7" id="two-factor">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-shield-lock me-1"></i> {{ __('app.two_factor') }}</span>
                @if($user->hasTwoFactor())<span class="badge bg-success">{{ __('app.enabled') }}</span>@else<span class="badge bg-secondary">{{ __('app.disabled') }}</span>@endif
            </div>
            <div class="card-body">
                <p class="small text-muted">{{ __('app.two_factor_intro') }}</p>

                @if($user->twoFactorRequired() && !$user->hasTwoFactor())
                    <div class="alert alert-warning small">{{ __('app.two_factor_setup_required') }}</div>
                @endif

                @if($recoveryCodes)
                    <div class="alert alert-warning">
                        <div class="fw-semibold mb-1"><i class="bi bi-key"></i> {{ __('app.recovery_codes_title') }}</div>
                        <div class="small mb-2">{{ __('app.recovery_codes_help') }}</div>
                        <div class="row row-cols-2 g-1 font-monospace">
                            @foreach($recoveryCodes as $code)<div class="col">{{ $code }}</div>@endforeach
                        </div>
                    </div>
                @endif

                @if($user->hasTwoFactor())
                    <p class="small mb-3">{{ __('app.two_factor_active_since', ['date' => $user->two_factor_confirmed_at->format(date_fmt())]) }} · {{ __('app.recovery_codes_left', ['count' => count($user->two_factor_recovery_codes ?? [])]) }}</p>
                    <div class="row g-2">
                        <form method="POST" action="{{ route('two-factor.recovery-codes') }}" class="col-md-6">
                            @csrf
                            <div class="input-group input-group-sm">
                                <input type="password" name="password" class="form-control" placeholder="{{ __('app.password') }}" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary">{{ __('app.recovery_codes_new') }}</button>
                            </div>
                        </form>
                        @unless($user->twoFactorRequired())
                        <form method="POST" action="{{ route('two-factor.disable') }}" class="col-md-6" data-confirm="{{ __('app.two_factor_disable_confirm') }}">
                            @csrf
                            <div class="input-group input-group-sm">
                                <input type="password" name="password" class="form-control" placeholder="{{ __('app.password') }}" required autocomplete="current-password">
                                <button class="btn btn-outline-danger">{{ __('app.two_factor_disable') }}</button>
                            </div>
                        </form>
                        @endunless
                    </div>
                    @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @elseif($pendingSecret)
                    <ol class="small ps-3">
                        <li>{{ __('app.two_factor_step_install') }}</li>
                        <li>{{ __('app.two_factor_step_scan') }}</li>
                    </ol>
                    <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                        <div class="bg-white p-2 border rounded">{!! $qr !!}</div>
                        <div class="small">
                            <div class="text-muted">{{ __('app.two_factor_manual_key') }}</div>
                            <div class="font-monospace fw-semibold text-break">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" class="form-control font-monospace @error('code') is-invalid @enderror mw-180px" placeholder="{{ __('app.two_factor_code') }}" required autofocus>
                        <button class="btn btn-primary">{{ __('app.two_factor_activate') }}</button>
                    </form>
                    @error('code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <form method="POST" action="{{ route('two-factor.cancel') }}" class="mt-2">@csrf<button class="btn btn-link btn-sm p-0">{{ __('app.cancel') }}</button></form>
                @else
                    <form method="POST" action="{{ route('two-factor.enable') }}" class="d-flex gap-2">
                        @csrf
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror mw-240px" placeholder="{{ __('app.password') }}" required autocomplete="current-password">
                        <button class="btn btn-primary"><i class="bi bi-shield-plus"></i> {{ __('app.two_factor_enable') }}</button>
                    </form>
                    @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-badge me-1"></i> {{ __('app.account_activity') }}</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.last_login') }}</span><span>{{ $user->last_login_at?->format(datetime_fmt()) ?? '—' }} @if($user->last_login_ip)<span class="text-muted">({{ $user->last_login_ip }})</span>@endif</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.password_changed_at') }}</span><span>{{ $user->password_changed_at?->format(date_fmt()) ?? '—' }}</span></li>
            </ul>
            <div class="card-footer small"><a href="{{ route('profile.edit') }}">{{ __('app.change_password') }}</a></div>
        </div>

        @if($sessionsSupported)
        <div class="card">
            <div class="card-header"><i class="bi bi-laptop me-1"></i> {{ __('app.active_sessions') }}</div>
            <ul class="list-group list-group-flush small">
                @foreach($sessions as $s)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $s->agent }} <span class="text-muted">· {{ $s->ip }}</span></span>
                        <span>@if($s->current)<span class="badge bg-success">{{ __('app.this_device') }}</span>@else{{ $s->last_activity->diffForHumans() }}@endif</span>
                    </li>
                @endforeach
            </ul>
            @if($sessions->where('current', false)->isNotEmpty())
            <div class="card-footer">
                <form method="POST" action="{{ route('two-factor.sessions.revoke') }}">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input type="password" name="password" class="form-control" placeholder="{{ __('app.password') }}" required autocomplete="current-password">
                        <button class="btn btn-outline-danger">{{ __('app.sessions_revoke_others') }}</button>
                    </div>
                </form>
            </div>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
