@extends("layouts.admin")
@section("title", __('app.services'))
@section("page_title", __('app.services_list'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $services->total()]) }}</span>
    <a href="{{ route("services.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_service') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("services.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search_services') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("services.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.service_name') }}</th>
                        <th>{{ __('app.type') }}</th>
                        <th>{{ __('app.price') }}</th>
                        <th>{{ __('app.estimated_days') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-medium">{{ $service->name }}</td>
                        <td>
                            @if($service->type == "by_weight")
                                <span class="badge bg-info"><i class="bi bi-box-seam"></i> {{ __('app.by_weight_type') }}</span>
                            @else
                                <span class="badge bg-warning text-dark"><i class="bi bi-box"></i> {{ __('app.by_piece_type') }}</span>
                            @endif
                        </td>
                        <td class="fw-semibold">{{ number_format($service->price, 0, ".", ",") }} Mts</td>
                        <td>{{ $service->estimated_days }} {{ __('app.days') }}</td>
                        <td class="text-center">
                            <a href="{{ route("services.edit", $service) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route("services.destroy", $service) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('app.delete_service_confirm') }}')">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            {{ __('app.no_services') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $services->links() }}
</div>
@endsection
