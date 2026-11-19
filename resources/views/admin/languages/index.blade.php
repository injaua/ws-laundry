@extends("layouts.admin")
@section("title", __('app.languages'))
@section("page_title", __('app.system_settings') . ' · ' . __('app.languages'))

@section("content")
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0">{{ __('app.languages_intro') }}</p>
    <a href="{{ route('admin.languages.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> {{ __('app.language_new') }}</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.language') }}</th>
                    <th>{{ __('app.code') }}</th>
                    <th>{{ __('app.text_direction') }}</th>
                    <th>{{ __('app.translation_progress') }}</th>
                    <th>{{ __('app.status') }}</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            @foreach($languages as $lang)
                @php
                    $done = (int) ($counts[$lang->code] ?? 0);
                    $pct = $total > 0 ? min(100, (int) floor($done * 100 / $total)) : 0;
                @endphp
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $lang->label() }}</span>
                        @if($lang->code === $default)<span class="badge bg-primary ms-1">{{ __('app.default') }}</span>@endif
                        @if($lang->is_system)<span class="badge bg-light text-dark ms-1">{{ __('app.language_system') }}</span>@endif
                    </td>
                    <td class="font-monospace">{{ $lang->code }}</td>
                    <td class="small">{{ $lang->isRtl() ? 'RTL' : 'LTR' }}</td>
                    <td class="minw-180px">
                        @if($lang->is_system)
                            <span class="small text-muted">{{ trans_choice('app.language_edited_texts', $done, ['count' => $done]) }}</span>
                        @else
                            <div class="progress h-6px"><div class="progress-bar {{ $pct === 100 ? 'bg-success' : '' }}" style="width: {{ $pct }}%"></div></div>
                            <span class="small text-muted">{{ $pct }}% · {{ __('app.language_based_on', ['base' => \App\Support\Languages::find($lang->base_locale)?->name ?? $lang->base_locale]) }}</span>
                        @endif
                    </td>
                    <td>@if($lang->is_active)<span class="badge bg-success">{{ __('app.active') }}</span>@else<span class="badge bg-secondary">{{ __('app.inactive') }}</span>@endif</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.languages.translations', $lang) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-translate"></i> {{ __('app.translate') }}</a>
                        <a href="{{ route('admin.languages.edit', $lang) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                        @if(!$lang->is_system && $lang->code !== $default)
                            <form method="POST" action="{{ route('admin.languages.destroy', $lang) }}" class="d-inline" data-confirm="{{ __('app.language_delete_confirm') }}">
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
@endsection
