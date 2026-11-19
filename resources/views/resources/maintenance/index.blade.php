@extends("layouts.admin")
@section("title", __('app.maintenance'))
@section("page_title", __('app.maintenance'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $jobs->total()]) }}</span>
    <a href="{{ route("resources.maintenance.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_maintenance') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("resources.maintenance.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <select name="equipment_id" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.equipment') }}</option>
                        @foreach($equipments as $equipment)
                            <option value="{{ $equipment->id }}" {{ request("equipment_id") == $equipment->id ? "selected" : "" }}>{{ $equipment->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="kind" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.type') }}</option>
                        @foreach(\App\Models\MaintenanceJob::KINDS as $kd)
                            <option value="{{ $kd }}" {{ request("kind") == $kd ? "selected" : "" }}>{{ __('app.maintenance_kind_' . $kd) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.status') }}</option>
                        @foreach(\App\Models\MaintenanceJob::STATUSES as $st)
                            <option value="{{ $st }}" {{ request("status") == $st ? "selected" : "" }}>{{ __('app.maintenance_status_' . $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <a href="{{ route("resources.maintenance.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.date') }}</th>
                        <th>{{ __('app.title') }}</th>
                        <th>{{ __('app.equipment') }}</th>
                        <th>{{ __('app.type') }}</th>
                        <th class="text-end">{{ __('app.cost') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.next_service_date') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                    <tr>
                        <td class="small">{{ $job->service_date->format(date_fmt()) }}</td>
                        <td class="fw-medium">
                            <a href="{{ route("resources.maintenance.show", $job) }}" class="text-decoration-none">{{ $job->title }}</a>
                        </td>
                        <td class="small">{{ $job->equipment->name ?? "—" }}</td>
                        <td class="small">{{ __('app.maintenance_kind_' . $job->kind) }}</td>
                        <td class="text-end small">{{ $job->cost > 0 ? money($job->cost) : "—" }}</td>
                        <td><span class="badge badge-status bg-{{ $job->status_color }}">{{ __('app.maintenance_status_' . $job->status) }}</span></td>
                        <td class="small">
                            @if($job->next_service_date)
                                <span class="{{ $job->next_service_date->isToday() || $job->next_service_date->isPast() ? "text-danger fw-semibold" : "" }}">{{ $job->next_service_date->format(date_fmt()) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route("resources.maintenance.show", $job) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                            <a href="{{ route("resources.maintenance.edit", $job) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route("resources.maintenance.destroy", $job) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-tools fs-1 d-block mb-2"></i>
                            {{ __('app.no_maintenance_jobs') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $jobs->links() }}</div>
@endsection
