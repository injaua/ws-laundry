@extends("layouts.admin")
@section("title", __('app.kpi_targets'))
@section("page_title", __('app.kpi_targets'))
@section("page_subtitle", __('app.kpi_targets_subtitle'))

@php($units = ["money" => currency_symbol(), "percent" => "%", "days" => __('app.days_short'), "hours" => "h", "kg" => "kg", "decimal" => "", "number" => ""])

@section("content")
<form method="POST" action="{{ route('management.targets.save') }}">
    @csrf @method('PUT')
    <div class="row g-3">
        @foreach(\App\Services\ManagementIndicators::GROUPS as $group)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">{{ __('app.kpi_group_' . $group) }}</div>
                    <div class="card-body">
                        @foreach($indicators as $key => $meta)
                            @continue($meta['group'] !== $group)
                            <div class="row g-2 align-items-center mb-2">
                                <label class="col-7 small" for="t_{{ $key }}">
                                    {{ __('app.kpi_' . $key) }}
                                    <span class="text-muted">({{ $meta['better'] === 'higher' ? __('app.kpi_min_target') : __('app.kpi_max_target') }})</span>
                                </label>
                                <div class="col-5">
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="any" min="0" name="targets[{{ $key }}]" id="t_{{ $key }}" class="form-control @error('targets.' . $key) is-invalid @enderror"
                                            value="{{ old('targets.' . $key, $targets[$key] ?? '') }}">
                                        @if($units[$meta['format']] ?? '')<span class="input-group-text">{{ $units[$meta['format']] }}</span>@endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <span class="small text-muted">{{ __('app.kpi_targets_help') }}</span>
        <div class="d-flex gap-2">
            <a href="{{ route('management.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
            <button class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
        </div>
    </div>
</form>
@endsection
