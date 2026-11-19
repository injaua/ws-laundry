@extends("layouts.admin")
@section("title", __('app.stores'))
@section("page_title", __('app.stores'))

@section("content")
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-shop-window me-1"></i> {{ __('app.stores') }}</span>
        <div class="d-flex gap-2">
            <a href="{{ route("admin.stores.overview") }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-bar-chart"></i> {{ __('app.stores_overview') }}
            </a>
            <a href="{{ route("admin.stores.create") }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> {{ __('app.store_new') }}
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.code') }}</th>
                        <th>{{ __('app.name') }}</th>
                        <th>{{ __('app.address') }}</th>
                        <th>{{ __('app.phone') }}</th>
                        <th class="text-center">{{ __('app.store_owners') }}</th>
                        <th class="text-center">{{ __('app.store_staff') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.readiness_short') }}</th>
                        <th class="text-end">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stores as $store)
                        <tr>
                            <td><span class="badge bg-secondary">{{ $store->code }}</span></td>
                            <td class="fw-medium">
                                {{ $store->name }}
                                @if($store->id === $currentStoreId)
                                    <span class="badge bg-primary-subtle text-primary ms-1">{{ __('app.store_current') }}</span>
                                @endif
                            </td>
                            <td class="small">{{ $store->address }}</td>
                            <td class="small">{{ $store->phone }}</td>
                            <td class="text-center">{{ $store->owners_count }}</td>
                            <td class="text-center">{{ $store->staff_count }}</td>
                            <td>
                                <span class="badge bg-{{ $store->is_active ? "success" : "secondary" }}">{{ $store->is_active ? __('app.active') : __('app.inactive') }}</span>
                            </td>
                            <td>
                                @php($ready = $readiness[$store->id] ?? null)
                                @if($ready)
                                    @php($issues = collect([...$ready['payments']['issues'], ...$ready['whatsapp']['issues']])->map(fn ($i) => __('app.' . $i[0], $i[1]))->join(' · '))
                                    <span class="badge bg-{{ ['ok' => 'success', 'warning' => 'warning text-dark', 'problem' => 'danger'][$ready['state']] }}" title="{{ $issues }}">{{ __('app.readiness_' . $ready['state']) }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($store->is_active && $store->id !== $currentStoreId)
                                    <form action="{{ route("admin.stores.switch", $store) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" title="{{ __('app.store_work_here') }}"><i class="bi bi-box-arrow-in-right"></i></button>
                                    </form>
                                @endif
                                <a href="{{ route("users.index", ["store" => $store->id]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.users') }}"><i class="bi bi-people"></i></a>
                                <a href="{{ route("admin.stores.edit", $store) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">{{ __('app.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted">
        <i class="bi bi-info-circle"></i> {{ __('app.stores_help') }}
    </div>
</div>
@endsection
