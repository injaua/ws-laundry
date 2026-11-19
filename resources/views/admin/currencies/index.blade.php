@extends("layouts.admin")
@section("title", __('app.currencies'))
@section("page_title", __('app.system_settings') . ' · ' . __('app.currencies'))

@section("content")
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">{{ __('app.currencies_intro') }}</p>
    <a href="{{ route('admin.currencies.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> {{ __('app.currency_new') }}</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>{{ __('app.code') }}</th><th>{{ __('app.name') }}</th><th>{{ __('app.currency_symbol') }}</th><th>{{ __('app.example') }}</th><th>{{ __('app.status') }}</th><th></th></tr>
            </thead>
            <tbody>
            @foreach($currencies as $cur)
                <tr>
                    <td class="font-monospace fw-semibold">{{ $cur->code }} @if($cur->code === $default)<span class="badge bg-primary ms-1">{{ __('app.default') }}</span>@endif</td>
                    <td>{{ $cur->name }}</td>
                    <td>{{ $cur->symbol }}</td>
                    <td class="small">{{ $cur->example() }}</td>
                    <td>@if($cur->is_active)<span class="badge bg-success">{{ __('app.active') }}</span>@else<span class="badge bg-secondary">{{ __('app.inactive') }}</span>@endif</td>
                    <td class="text-end text-nowrap">
                        @if($cur->code !== $default && $cur->is_active)
                            <form method="POST" action="{{ route('admin.currencies.default', $cur) }}" class="d-inline" data-confirm="{{ __('app.currency_default_confirm', ['code' => $cur->code]) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary">{{ __('app.make_default') }}</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.currencies.edit', $cur) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                        @if($cur->code !== $default)
                            <form method="POST" action="{{ route('admin.currencies.destroy', $cur) }}" class="d-inline" data-confirm="{{ __('app.currency_delete_confirm') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="alert alert-warning small mt-3"><i class="bi bi-exclamation-triangle"></i> {{ __('app.currency_no_conversion') }}</div>
@endsection
