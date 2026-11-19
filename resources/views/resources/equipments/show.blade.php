@extends("layouts.admin")
@section("title", $equipment->name)
@section("page_title", __('app.equipment') . ": " . $equipment->name)

@section("content")
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
                        <tr><td class="text-muted">{{ __('app.purchase_date') }}</td><td>{{ $equipment->purchase_date ? $equipment->purchase_date->format(date_fmt()) : "—" }}</td></tr>
                        <tr><td class="text-muted">{{ __('app.purchase_price') }}</td><td>{{ $equipment->purchase_price !== null ? money($equipment->purchase_price) : "—" }}</td></tr>
                        @if($equipment->capacity_kg)
                            <tr><td class="text-muted">{{ __('app.capacity_kg') }}</td><td>{{ rtrim(rtrim($equipment->capacity_kg, "0"), ".") }} kg</td></tr>
                        @endif
                        @if(in_array($equipment->type, ["washer", "dryer"]))
                            <tr><td class="text-muted">{{ __('app.cycles') }}</td><td>{{ $equipment->cycles_count }}
                                @if($equipment->service_interval_cycles)
                                    <div class="small {{ $equipment->serviceDue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ __('app.cycles_since_service', ['n' => $equipment->cyclesSinceService(), 'interval' => $equipment->service_interval_cycles]) }}</div>
                                @endif
                            </td></tr>
                        @endif
                        <tr><td class="text-muted">{{ __('app.warranty_until') }}</td><td>{{ $equipment->warranty_until ? $equipment->warranty_until->format(date_fmt()) : "—" }}</td></tr>
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
                                <td class="small">{{ $job->service_date->format(date_fmt()) }}</td>
                                <td><a href="{{ route("resources.maintenance.show", $job) }}" class="text-decoration-none fw-medium">{{ $job->title }}</a></td>
                                <td class="small">{{ __('app.maintenance_kind_' . $job->kind) }}</td>
                                <td class="small">{{ $job->cost > 0 ? money($job->cost) : "—" }}</td>
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

    @if(in_array($equipment->type, ["washer", "dryer"]))
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-droplet me-1"></i> {{ __('app.cycle_recipe') }}</div>
                <div class="card-body">
                    <p class="small text-muted">{{ __('app.cycle_recipe_hint') }}</p>
                    <table class="table table-sm align-middle">
                        <tbody>
                        @forelse($equipment->recipes as $recipe)
                            <tr>
                                <td>{{ $recipe->item?->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim($recipe->quantity, "0"), ".") }} {{ $recipe->item?->unit }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('resources.equipments.recipes.destroy', [$equipment, $recipe]) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-muted small">{{ __('app.no_recipe') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    <form method="POST" action="{{ route('resources.equipments.recipes.store', $equipment) }}" class="row g-2">
                        @csrf
                        <div class="col-7">
                            <select name="inventory_item_id" class="form-select form-select-sm" required>
                                <option value="">{{ __('app.select_item') }}</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-3"><input type="number" name="quantity" step="0.001" min="0.001" class="form-control form-control-sm" placeholder="{{ __('app.quantity') }}" required></div>
                        <div class="col-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-arrow-repeat me-1"></i> {{ __('app.recent_loads') }}</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead><tr><th>{{ __('app.load') }}</th><th>{{ __('app.started_at') }}</th><th class="text-end">kg</th><th>{{ __('app.operator') }}</th><th>{{ __('app.status') }}</th></tr></thead>
                        <tbody>
                        @forelse($loads as $load)
                            <tr>
                                <td><a href="{{ route('production.loads.show', $load) }}">{{ $load->code }}</a> <span class="small text-muted">({{ $load->orders_count }})</span></td>
                                <td class="small">{{ $load->started_at?->format("d/m H:i") }}</td>
                                <td class="text-end">{{ rtrim(rtrim($load->weight_kg, "0"), ".") }}</td>
                                <td class="small">{{ $load->employee?->name ?? "—" }}</td>
                                <td><span class="badge bg-{{ $load->statusColor() }}">{{ __('app.load_status_' . $load->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">{{ __('app.no_loads') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
