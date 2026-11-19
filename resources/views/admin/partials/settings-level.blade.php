{{--
    Which configuration a settings page edits: the company's (used by every store without its own)
    or the store worked in. $bar comes from ChoosesSettingsLevel::levelBar(); $release is the route
    that sends the store back to the company configuration.
--}}
@php($onStore = $bar['level'] !== null)
@if(!auth()->user()->isAdmin())
{{-- The store owner configures their own store only. --}}
<div class="card mb-3">
    <div class="card-body py-2 small">
        <span class="fw-semibold me-1">{{ __('app.settings_level_editing') }}</span>
        <span class="badge bg-info text-dark"><i class="bi bi-shop"></i> {{ __('app.settings_level_store', ['name' => $bar['store']?->name]) }}</span>
        <div class="text-muted mt-2">{{ $bar['owns'] ? __('app.settings_level_store_own_help') : __('app.settings_level_owner_new_help') }}</div>
    </div>
</div>
@else
<div class="card mb-3">
    <div class="card-body py-2 small">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold me-1">{{ __('app.settings_level_editing') }}</span>
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route($bar['route'], ['level' => 'company']) }}" class="btn {{ $onStore ? 'btn-outline-secondary' : 'btn-primary' }}">
                    <i class="bi bi-buildings"></i> {{ __('app.settings_level_company') }}
                </a>
                @if($bar['store'])
                    <a href="{{ route($bar['route'], ['level' => 'store']) }}" class="btn {{ $onStore ? 'btn-primary' : 'btn-outline-secondary' }}">
                        <i class="bi bi-shop"></i> {{ __('app.settings_level_store', ['name' => $bar['store']->name]) }}
                    </a>
                @endif
            </div>
            @if($bar['store'])
                <span class="badge {{ $bar['owns'] ? 'bg-info text-dark' : 'bg-secondary' }}">
                    {{ $bar['owns'] ? __('app.settings_level_store_own', ['name' => $bar['store']->name]) : __('app.settings_level_store_inherits', ['name' => $bar['store']->name]) }}
                </span>
            @endif
        </div>

        <div class="text-muted mt-2">
            @if(!$onStore)
                {{ __('app.settings_level_company_help') }}
                @if($bar['ownStores']->isNotEmpty())
                    {{ __('app.settings_level_company_except', ['stores' => $bar['ownStores']->pluck('name')->join(', ')]) }}
                @endif
            @elseif($bar['owns'])
                {{ __('app.settings_level_store_own_help') }}
            @else
                {{ __('app.settings_level_store_new_help') }}
            @endif
        </div>

        @if($onStore && $bar['owns'])
            <form action="{{ route($release) }}" method="POST" class="mt-2" data-confirm="{{ __('app.settings_level_release_confirm') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-counterclockwise"></i> {{ __('app.settings_level_release') }}</button>
            </form>
        @endif
    </div>
</div>
@endif
