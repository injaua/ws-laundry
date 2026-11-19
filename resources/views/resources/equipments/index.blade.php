@extends("layouts.admin")
@section("title", __('app.equipments'))
@section("page_title", __('app.equipments'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $equipments->total()]) }}</span>
    <a href="{{ route("resources.equipments.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_equipment') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("resources.equipments.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.equipment_type') }}</option>
                        @foreach(\App\Models\Equipment::TYPES as $tp)
                            <option value="{{ $tp }}" {{ request("type") == $tp ? "selected" : "" }}>{{ __('app.eq_type_' . $tp) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.status') }}</option>
                        @foreach(\App\Models\Equipment::STATUSES as $st)
                            <option value="{{ $st }}" {{ request("status") == $st ? "selected" : "" }}>{{ __('app.status_' . $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("resources.equipments.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.equipment') }}</th>
                        <th>{{ __('app.equipment_type') }}</th>
                        <th>{{ __('app.location') }}</th>
                        <th>{{ __('app.warranty_until') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.maintenance') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($equipments as $equipment)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-medium">
                            <a href="{{ route("resources.equipments.show", $equipment) }}" class="text-decoration-none">{{ $equipment->name }}</a>
                            @if($equipment->code)
                                <br><small class="text-muted">{{ $equipment->code }}</small>
                            @endif
                        </td>
                        <td class="small">{{ __('app.eq_type_' . $equipment->type) }}</td>
                        <td class="small">{{ $equipment->location ?: "—" }}</td>
                        <td class="small">{{ $equipment->warranty_until ? $equipment->warranty_until->format(date_fmt()) : "—" }}</td>
                        <td>
                            <span class="badge badge-status bg-{{ $equipment->status_color }}">{{ __('app.status_' . $equipment->status) }}</span>
                        </td>
                        <td class="text-center small">{{ $equipment->jobs_count }}</td>
                        <td class="text-center">
                            <a href="{{ route("resources.equipments.show", $equipment) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                            <a href="{{ route("resources.equipments.edit", $equipment) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route("resources.equipments.destroy", $equipment) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-gear-wide-connected fs-1 d-block mb-2"></i>
                            {{ __('app.no_equipments') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $equipments->links() }}</div>
@endsection
