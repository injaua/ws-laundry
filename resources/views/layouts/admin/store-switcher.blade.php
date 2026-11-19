{{-- Top bar · the store worked in. Admins switch between stores; everyone else sees theirs. --}}
@if($currentStore)
    @if($switchableStores->count() > 1)
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle text-truncate store-switcher" data-bs-toggle="dropdown" title="{{ __('app.store_switch') }}">
                <i class="bi bi-shop-window"></i> {{ $currentStore->name }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">{{ __('app.store_switch') }}</h6></li>
                @foreach($switchableStores as $store)
                    <li>
                        <form action="{{ route("admin.stores.switch", $store) }}" method="POST">
                            @csrf
                            <button class="dropdown-item {{ $store->id === $currentStore->id ? "active" : "" }}">{{ $store->label() }}</button>
                        </form>
                    </li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route("admin.stores.overview") }}"><i class="bi bi-bar-chart me-2"></i>{{ __('app.stores_overview') }}</a></li>
                <li><a class="dropdown-item" href="{{ route("admin.stores.index") }}"><i class="bi bi-gear me-2"></i>{{ __('app.stores_manage') }}</a></li>
            </ul>
        </div>
    @else
        <span class="badge text-bg-light border d-none d-sm-inline-flex align-items-center gap-1 text-truncate store-switcher" title="{{ __('app.store') }}">
            <i class="bi bi-shop-window"></i> {{ $currentStore->name }}
        </span>
    @endif
@endif
