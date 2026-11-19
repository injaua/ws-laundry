@extends("layouts.admin")

@section("title", __('app.activity_log'))
@section("page_title", __('app.activity_logs_title'))

@push("styles")
    @vite("resources/css/pages/activity-logs.css")
@endpush

@section("content")
<div class="filter-card">
    <form method="GET" action="{{ route("activity-logs.index") }}" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">{{ __('app.action_type') }}</label>
            <select name="action" class="form-select">
                <option value="">{{ __('app.all_actions') }}</option>
                @foreach($actionTypes as $type)
                    <option value="{{ $type }}" {{ request("action") == $type ? "selected" : "" }}>
                        {{ __('app.log_action_' . $type) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">{{ __('app.subject_type') }}</label>
            <select name="subject_type" class="form-select">
                <option value="">{{ __('app.all_subjects') }}</option>
                @foreach($subjectTypes as $type)
                    <option value="{{ $type }}" {{ request("subject_type") == $type ? "selected" : "" }}>
                        {{ $type }}
                    </option>
                @endforeach
            </select>
        </div>
        @if($stores->isNotEmpty())
        <div class="col-md-4">
            <label class="form-label small fw-semibold">{{ __('app.store') }}</label>
            <select name="store" class="form-select">
                <option value="">{{ __('app.stores_all') }}</option>
                <option value="global" @selected(request("store") === "global")>{{ __('app.log_company_wide') }}</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" @selected(request("store") == $store->id)>{{ $store->label() }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-funnel"></i> {{ __('app.filter') }}
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1"></i> {{ __('app.activity_logs_title') }}</span>
        <span class="d-flex gap-2">
            @if($integrity['ok'])
                <span class="badge bg-success" title="{{ __('app.audit_intact_help') }}"><i class="bi bi-shield-check"></i> {{ __('app.audit_intact') }}</span>
            @else
                <span class="badge bg-danger" title="{{ __('app.audit_broken_help') }}"><i class="bi bi-shield-exclamation"></i> {{ __('app.audit_broken', ['id' => $integrity['broken_id']]) }}</span>
            @endif
            <span class="badge bg-secondary">{{ __('app.total_count', ['count' => $logs->total()]) }}</span>
        </span>
    </div>
    <div class="card-body p-0">
        @if($logs->count())
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.time') }}</th>
                            @if($stores->isNotEmpty())<th>{{ __('app.store') }}</th>@endif
                            <th>{{ __('app.user') }}</th>
                            <th>{{ __('app.actions') }}</th>
                            <th>{{ __('app.subject_type') }}</th>
                            <th>{{ __('app.description') }}</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr class="log-entry">
                                <td class="log-time">
                                    <i class="bi bi-clock me-1"></i>
                                    {{ $log->created_at->format(datetime_fmt()) }}
                                </td>
                                @if($stores->isNotEmpty())
                                    <td class="small">
                                        @if($log->store){{ $log->store->name }}@else<span class="text-muted fst-italic">{{ __('app.log_company_wide') }}</span>@endif
                                    </td>
                                @endif
                                <td>
                                    @if($log->actor)
                                        <span class="fw-medium">{{ $log->actor->name }}</span>
                                        <span class="badge bg-light text-dark ms-1">{{ __('app.role_' . $log->actor->role) }}</span>
                                    @else
                                        <span class="text-muted fst-italic">System</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match($log->action) {
                                            "created" => "bg-success",
                                            "updated" => "bg-primary",
                                            "deleted" => "bg-danger",
                                            "login_failed", "lockout", "two_factor_failed" => "bg-danger",
                                            "login", "logout" => "bg-info text-dark",
                                            default => "bg-secondary",
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} log-action">{{ __('app.log_action_' . $log->action) }}</span>
                                </td>
                                <td>
                                    <span class="fw-medium">{{ class_basename($log->subject_type) }}</span>
                                    <span class="text-muted">#{{ $log->subject_id }}</span>
                                </td>
                                <td class="log-desc">{{ $log->description }}</td>
                                <td class="small text-muted" title="{{ $log->user_agent }}">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $logs->withQueryString()->links() }}
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox text-2rem"></i>
                <p class="mt-2 mb-0">{{ __('app.no_activity_logs') }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
