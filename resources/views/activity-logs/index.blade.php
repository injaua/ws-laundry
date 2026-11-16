@extends("layouts.admin")

@section("title", __('app.activity_log'))
@section("page_title", __('app.activity_logs_title'))

@push("styles")
<style>
    .log-entry { transition: background-color 0.15s; }
    .log-entry:hover { background-color: #f8f9fa; }
    [data-bs-theme="dark"] .log-entry:hover { background-color: rgba(255,255,255,0.03); }
    .log-action { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
    .log-time { font-size: 0.8rem; color: #6c757d; }
    [data-bs-theme="dark"] .log-time { color: var(--text-muted); }
    .log-desc { font-weight: 500; overflow-wrap: anywhere; }
    .filter-card { background: #fff; border-radius: 12px; border: 1px solid #e9ecef; padding: 1.25rem; margin-bottom: 1.5rem; }
    [data-bs-theme="dark"] .filter-card { background: var(--card-bg); border-color: var(--card-border); }
</style>
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
                        {{ ucfirst($type) }}
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
        <span class="badge bg-secondary">{{ __('app.total_count', ['count' => $logs->total()]) }}</span>
    </div>
    <div class="card-body p-0">
        @if($logs->count())
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.time') }}</th>
                            <th>{{ __('app.user') }}</th>
                            <th>{{ __('app.actions') }}</th>
                            <th>{{ __('app.subject_type') }}</th>
                            <th>{{ __('app.description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr class="log-entry">
                                <td class="log-time">
                                    <i class="bi bi-clock me-1"></i>
                                    {{ $log->created_at->format("d/m/Y H:i") }}
                                </td>
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
                                            default => "bg-secondary",
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} log-action">{{ $log->action }}</span>
                                </td>
                                <td>
                                    <span class="fw-medium">{{ class_basename($log->subject_type) }}</span>
                                    <span class="text-muted">#{{ $log->subject_id }}</span>
                                </td>
                                <td class="log-desc">{{ $log->description }}</td>
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
                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0">{{ __('app.no_activity_logs') }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
