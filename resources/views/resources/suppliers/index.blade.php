@extends("layouts.admin")
@section("title", __('app.suppliers'))
@section("page_title", __('app.suppliers'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $suppliers->total()]) }}</span>
    @if(auth()->user()->isManager())
    <a href="{{ route("resources.suppliers.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_supplier') }}
    </a>
    @endif
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("resources.suppliers.index") }}" method="GET" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="{{ __('app.search_placeholder') }}" value="{{ request("search") }}">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> {{ __('app.search') }}</button>
                </div>
                <div class="col-auto ms-md-3">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="inactive" value="1" id="inactive" {{ request()->boolean("inactive") ? "checked" : "" }} data-auto-submit>
                        <label class="form-check-label small" for="inactive">{{ __('app.show_inactive') }}</label>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.name') }}</th>
                        <th>{{ __('app.contact_person') }}</th>
                        <th>{{ __('app.phone') }}</th>
                        <th>{{ __('app.city') }}</th>
                        <th class="text-end">{{ __('app.purchase_orders') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                    <tr>
                        <td class="fw-medium">
                            <a href="{{ route("resources.suppliers.show", $supplier) }}" class="text-decoration-none">{{ $supplier->name }}</a>
                        </td>
                        <td class="small">{{ $supplier->contact_person ?: "—" }}</td>
                        <td class="small">{{ $supplier->phone ?: "—" }}</td>
                        <td class="small">{{ $supplier->city ?: "—" }}</td>
                        <td class="text-end small">{{ $supplier->purchase_orders_count }}</td>
                        <td>
                            @if($supplier->is_active)
                                <span class="badge bg-success-subtle text-success">{{ __('app.active') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('app.inactive') }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route("resources.suppliers.show", $supplier) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                            @if(auth()->user()->isManager())
                            <a href="{{ route("resources.suppliers.edit", $supplier) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route("resources.suppliers.destroy", $supplier) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-truck fs-1 d-block mb-2"></i>
                            {{ __('app.no_suppliers') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $suppliers->links() }}</div>
@endsection
