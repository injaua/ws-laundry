@extends("layouts.admin")
@php
    $kindLabels = [
        "product" => __('app.products'),
        "material" => __('app.materials'),
        "part" => __('app.spare_parts'),
    ];
@endphp
@section("title", $kindLabels[$kind])
@section("page_title", $kindLabels[$kind])

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $items->total()]) }}</span>
    <a href="{{ route("resources.items.create", ["kind" => $kind]) }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_item') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("resources.items.index") }}" method="GET" class="row g-2">
                <input type="hidden" name="kind" value="{{ $kind }}">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="low_stock" {{ request("low_stock") ? "checked" : "" }}>
                        <label class="form-check-label" for="low_stock">{{ __('app.low_stock') }}</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("resources.items.index", ["kind" => $kind]) }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.name') }}</th>
                        <th>{{ __('app.type') }}</th>
                        <th class="text-end">{{ __('app.stock_qty') }}</th>
                        <th class="text-end">{{ __('app.min_stock_qty') }}</th>
                        <th class="text-end">{{ __('app.unit_cost') }}</th>
                        <th>{{ __('app.supplier') }}</th>
                        <th class="text-center">{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-medium">
                            <a href="{{ route("resources.items.show", $item) }}" class="text-decoration-none">{{ $item->name }}</a>
                        </td>
                        <td class="small">{{ $item->type ? __('app.item_type_' . $item->type) : "—" }}</td>
                        <td class="text-end">
                            <span class="{{ $item->isLowStock() ? "text-danger fw-bold" : "" }}">{{ qty($item->stock_qty) }} {{ __('app.unit_' . $item->unit) }}</span>
                            @if($item->isLowStock())
                                <i class="bi bi-exclamation-triangle text-danger" title="{{ __('app.low_stock') }}"></i>
                            @endif
                        </td>
                        <td class="text-end small">{{ $item->min_stock_qty > 0 ? qty($item->min_stock_qty) : "—" }}</td>
                        <td class="text-end small">{{ $item->unit_cost > 0 ? num($item->unit_cost) : "—" }}</td>
                        <td class="small">{{ $item->supplier ?: "—" }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $item->is_active ? "success" : "secondary" }}">{{ $item->is_active ? __('app.active') : __('app.inactive') }}</span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route("resources.items.show", $item) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-list-ol"></i></a>
                            <a href="{{ route("resources.items.edit", $item) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route("resources.items.destroy", $item) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="bi bi-boxes fs-1 d-block mb-2"></i>
                            {{ __('app.no_items') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $items->links() }}</div>
@endsection
