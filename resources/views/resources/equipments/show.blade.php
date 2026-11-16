@extends("layouts.admin")
@section("title", $equipment->name)
@section("page_title", __('app.equipment') . ": " . $equipment->name)

@section("content")
@php($fmt = fn ($v) => number_format($v, 0, ".", ","))
<div class="page-title">
    <span>
        <span class="badge badge-status bg-{{ $equipment->status_color }}">{{ __('app.status_' . $equipment->status) }}</span>
        @if($equipment->code)<small class="text-muted ms-2">{{ $equipment->code }}</small>@endif
    </span>
    <div>
        <a href="{{ route("resources.maintenance.create", ["equipment_id" => $equipment->id]) }}" class="btn btn-outline-primary">
            <i class="bi bi-tools"></i> {{ __('app.new_maintenance') }}
        </a>
        <a href="{{ route("resources.equipments.edit", $equipment) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> {{ __('app.edit') }}</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">{{ __('app.equipment') }}</div>
            <div class="card-body">
                @if($equipment->photo)
                    <img src="{{ asset('storage/' . $equipment->photo) }}" alt="{{ $equipment->name }}" class="img-fluid rounded border mb-3">
                @endif
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="text-muted">{{ __('app.equipment_type') }}</td><td class="fw-medium">{{ __('app.eq_type_' . $equipment->type) }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.brand') }}</td><td>{{ $equipment->brand ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.model') }}</td><td>{{ $equipment->model ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.serial_number') }}</td><td>{{ $equipment->serial_number ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.location') }}</td><td>{{ $equipment->location ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.supplier') }}</td><td>{{ $equipment->supplier ?: "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.purchase_date') }}</td><td>{{ $equipment->purchase_date ? $equipment->purchase_date->format("d/m/Y") : "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.purchase_price') }}</td><td>{{ $equipment->purchase_price !== null ? $fmt($equipment->purchase_price) . " Mts" : "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.warranty_until') }}</td><td>{{ $equipment->warranty_until ? $equipment->warranty_until->format("d/m/Y") : "—" }}</td></tr>
                    </tbody>
                </table>
                @if($equipment->notes)
                    <p class="small text-muted mt-2 mb-0">{{ $equipment->notes }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">{{ __('app.maintenance_history') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.date') }}</th>
                                <th>{{ __('app.title') }}</th>
                                <th>{{ __('app.type') }}</th>
                                <th>{{ __('app.cost') }}</th>
                                <th>{{ __('app.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($equipment->jobs as $job)
                            <tr>
                                <td class="small">{{ $job->service_date->format("d/m/Y") }}</td>
                                <td><a href="{{ route("resources.maintenance.show", $job) }}" class="text-decoration-none fw-medium">{{ $job->title }}</a></td>
                                <td class="small">{{ __('app.maintenance_kind_' . $job->kind) }}</td>
                                <td class="small">{{ $job->cost > 0 ? $fmt($job->cost) . " Mts" : "—" }}</td>
                                <td><span class="badge badge-status bg-{{ $job->status_color }}">{{ __('app.maintenance_status_' . $job->status) }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">{{ __('app.no_maintenance_jobs') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
