@extends("layouts.admin")
@section("title", __('app.security_compliance'))
@section("page_title", __('app.security_compliance'))

@section("content")
{{-- Summary --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card border-{{ $score['critical'] ? 'danger' : 'success' }}"><div class="card-body py-2">
        <div class="small text-muted">{{ __('app.security_critical') }}</div><div class="fs-4 fw-bold text-{{ $score['critical'] ? 'danger' : 'success' }}">{{ $score['critical'] }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-{{ $score['warning'] ? 'warning' : 'success' }}"><div class="card-body py-2">
        <div class="small text-muted">{{ __('app.security_warnings') }}</div><div class="fs-4 fw-bold text-{{ $score['warning'] ? 'warning' : 'success' }}">{{ $score['warning'] }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">
        <div class="small text-muted">{{ __('app.security_checks_ok') }}</div><div class="fs-4 fw-bold">{{ $score['ok'] }}/{{ $score['total'] }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-{{ $failedLast24h > 10 ? 'danger' : 'light' }}"><div class="card-body py-2">
        <div class="small text-muted">{{ __('app.failed_logins_24h') }}</div><div class="fs-4 fw-bold {{ $failedLast24h > 10 ? 'text-danger' : '' }}">{{ $failedLast24h }}</div>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        {{-- Configuration review --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clipboard-check me-1"></i> {{ __('app.security_review') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($checks as $c)
                    @php
                        $icon = $c['ok'] ? 'check-circle-fill text-success' : ($c['level'] === 'critical' ? 'x-octagon-fill text-danger' : ($c['level'] === 'warning' ? 'exclamation-triangle-fill text-warning' : 'info-circle-fill text-info'));
                    @endphp
                    <li class="list-group-item small d-flex gap-2">
                        <i class="bi bi-{{ $icon }}"></i>
                        <div>
                            <div class="fw-semibold">{{ __('app.check_' . $c['key']) }} @if($c['detail'])<span class="text-muted fw-normal">· {{ $c['detail'] }}</span>@endif</div>
                            @unless($c['ok'])<div class="text-muted">{{ __('app.check_' . $c['key'] . '_fix') }}</div>@endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Staff accounts --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-people me-1"></i> {{ __('app.staff_accounts') }}</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('app.name') }}</th><th>{{ __('app.role') }}</th><th>{{ __('app.two_factor') }}</th><th>{{ __('app.last_login') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach($users as $u)
                        <tr class="{{ $u->is_active ? '' : 'text-muted' }}">
                            <td>{{ $u->name }}<div class="small text-muted">{{ $u->email }}</div></td>
                            <td class="small">{{ __('app.role_' . $u->role) }}@unless($u->is_active) · {{ __('app.inactive') }}@endunless</td>
                            <td>
                                @if($u->hasTwoFactor())<span class="badge bg-success">{{ __('app.enabled') }}</span>
                                @elseif(in_array($u->role, $twoFactorRoles, true))<span class="badge bg-warning text-dark">{{ __('app.two_factor_pending') }}</span>
                                @else<span class="badge bg-secondary">{{ __('app.disabled') }}</span>@endif
                            </td>
                            <td class="small">{{ $u->last_login_at?->format(datetime_fmt()) ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                @if($u->hasTwoFactor() && !$u->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.security.users.two-factor.reset', $u) }}" class="d-inline" data-confirm="{{ __('app.two_factor_reset_confirm') }}">@csrf
                                        <button class="btn btn-sm btn-outline-warning" title="{{ __('app.two_factor_reset') }}"><i class="bi bi-phone-flip"></i></button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.security.users.sessions.revoke', $u) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-outline-secondary" title="{{ __('app.sessions_end_all') }}"><i class="bi bi-box-arrow-right"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent security events --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-activity me-1"></i> {{ __('app.security_events') }}</span>
                <a href="{{ route('activity-logs.index', ['action' => 'security']) }}" class="small">{{ __('app.view_all') }}</a>
            </div>
            <ul class="list-group list-group-flush small">
                @forelse($events as $e)
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span><span class="badge bg-{{ in_array($e->action, ['login_failed', 'lockout', 'two_factor_failed'], true) ? 'danger' : 'light text-dark' }}">{{ __('app.log_action_' . $e->action) }}</span> {{ $e->description }}</span>
                        <span class="text-muted text-nowrap">{{ $e->created_at->format('d/m H:i') }} @if($e->ip_address)· {{ $e->ip_address }}@endif</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">{{ __('app.no_records') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-5">
        {{-- Policies --}}
        <form method="POST" action="{{ route('admin.security.settings') }}" class="card mb-3">
            @csrf @method('PUT')
            <div class="card-header"><i class="bi bi-sliders me-1"></i> {{ __('app.security_policies') }}</div>
            <div class="card-body">
                <label class="form-label">{{ __('app.two_factor_required_roles') }}</label>
                <div class="mb-1">
                    @foreach(\App\Services\Security\SecuritySettings::ROLES as $role)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="two_factor_roles[]" value="{{ $role }}" id="tfr_{{ $role }}" @checked(in_array($role, $twoFactorRoles, true))>
                            <label class="form-check-label" for="tfr_{{ $role }}">{{ __('app.role_' . $role) }}</label>
                        </div>
                    @endforeach
                </div>
                <div class="form-text mb-3">{{ __('app.two_factor_required_roles_help') }}</div>

                <label class="form-label" for="idle_minutes">{{ __('app.idle_minutes') }}</label>
                <input type="number" name="idle_minutes" id="idle_minutes" min="0" max="1440" value="{{ old('idle_minutes', $idleMinutes) }}" class="form-control mb-1 @error('idle_minutes') is-invalid @enderror">
                <div class="form-text mb-3">{{ __('app.idle_minutes_help') }}</div>

                <label class="form-label" for="backup_retention_days">{{ __('app.backup_retention_days') }}</label>
                <input type="number" name="backup_retention_days" id="backup_retention_days" min="1" max="3650" value="{{ old('backup_retention_days', $backupRetention) }}" class="form-control mb-3">

                <label class="form-label" for="fiscal_retention_years">{{ __('app.fiscal_retention_years') }}</label>
                <input type="number" name="fiscal_retention_years" id="fiscal_retention_years" min="1" max="30" value="{{ old('fiscal_retention_years', $fiscalRetention) }}" class="form-control mb-1">
                <div class="form-text">{{ __('app.fiscal_retention_years_help') }}</div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> {{ __('app.save') }}</button></div>
        </form>

        {{-- Backups --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-database-check me-1"></i> {{ __('app.backups') }}</span>
                @if($backupsSupported)
                    <form method="POST" action="{{ route('admin.security.backups.run') }}">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('app.backup_now') }}</button></form>
                @endif
            </div>
            @if(!$backupsSupported)
                <div class="card-body small text-muted">{{ __('app.backup_unsupported') }}</div>
            @else
                <ul class="list-group list-group-flush small">
                    @forelse($backups as $b)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $b->created_at->format(datetime_fmt()) }} <span class="text-muted">· {{ number_format($b->size / 1024, 0) }} KB</span></span>
                            <a href="{{ route('admin.security.backups.download', $b->name) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.download') }}"><i class="bi bi-download"></i></a>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('app.no_backups') }}</li>
                    @endforelse
                </ul>
                <div class="card-footer small text-muted">{{ __('app.backup_help') }}</div>
            @endif
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-person-lock me-1"></i> {{ __('app.personal_data') }}</div>
            <div class="card-body small text-muted">{{ __('app.personal_data_help') }}</div>
        </div>
    </div>
</div>
@endsection
