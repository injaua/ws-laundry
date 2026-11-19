@extends("layouts.admin")
@section("title", __('app.delivery_zones'))
@section("page_title", __('app.delivery_zones'))
@section("page_subtitle", __('app.delivery_zones_subtitle') . " · " . __('app.zones_of_store', ["store" => \App\Support\CurrentStore::store()?->name]))

@section("content")
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route("logistics.index") }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.logistics') }}</a>
    <a href="{{ route("delivery-zones.create") }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('app.new_zone') }}</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.name') }}</th>
                        <th class="text-end">{{ __('app.fee_per_trip') }}</th>
                        <th class="text-end">{{ __('app.free_from') }}</th>
                        <th class="text-center">{{ __('app.customers') }}</th>
                        <th class="text-center">{{ __('app.trips') }}</th>
                        <th class="text-center">{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($zones as $zone)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $zone->name }}</div>
                            @if($zone->description)<div class="small text-muted">{{ $zone->description }}</div>@endif
                        </td>
                        <td class="text-end">{{ money($zone->fee) }}</td>
                        <td class="text-end">{{ $zone->free_from !== null ? money($zone->free_from) : "—" }}</td>
                        <td class="text-center">{{ $zone->customers_count }}</td>
                        <td class="text-center">{{ $zone->jobs_count }}</td>
                        <td class="text-center"><span class="badge bg-{{ $zone->is_active ? "success" : "secondary" }}">{{ $zone->is_active ? __('app.active') : __('app.inactive') }}</span></td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route("delivery-zones.edit", $zone) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route("delivery-zones.destroy", $zone) }}" method="POST" class="d-inline" data-confirm="{{ __('app.confirm_delete') }}">
                                @csrf @method("DELETE")
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('app.no_zones') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
