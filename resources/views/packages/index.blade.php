@extends("layouts.admin")
@section("title", __('app.packages'))
@section("page_title", __('app.packages_list'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $packages->total()]) }}</span>
    <a href="{{ route("packages.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_package') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("packages.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search_packages') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("packages.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.package_name') }}</th>
                        <th>{{ __('app.description') }}</th>
                        <th>{{ __('app.package_weight') }}</th>
                        <th>{{ __('app.price') }}</th>
                        <th class="text-center">{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($packages as $package)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-medium">{{ $package->name }}</td>
                        <td><small class="text-muted">{{ $package->description ? Str::limit($package->description, 50) : "-" }}</small></td>
                        <td>{{ number_format($package->weight_kg, 2, ".", ",") }} kg</td>
                        <td class="fw-semibold">{{ number_format($package->price, 0, ".", ",") }} Mts</td>
                        <td class="text-center">
                            @if($package->is_active)
                                <span class="badge bg-success">{{ __('app.active') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('app.no') }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route("packages.edit", $package) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route("packages.destroy", $package) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('app.delete_package_confirm') }}')">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
                            {{ __('app.no_packages') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $packages->links() }}</div>
@endsection
