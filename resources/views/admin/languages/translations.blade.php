@extends("layouts.admin")
@section("title", __('app.translate') . ' · ' . $language->name)
@section("page_title", __('app.translate') . ' · ' . $language->label())

@section("content")
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="small text-muted">
        @if($language->is_system)
            {{ __('app.translations_system_intro') }}
        @else
            {{ __('app.translations_intro', ['base' => $baseName]) }}
        @endif
        <span class="ms-1 fw-semibold">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.languages.export', $language) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> {{ __('app.translations_export') }}</a>
        <form method="POST" action="{{ route('admin.languages.import', $language) }}" enctype="multipart/form-data" class="d-flex gap-1">
            @csrf
            <input type="file" name="file" accept=".json,application/json" class="form-control form-control-sm mw-200px" required>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-upload"></i> {{ __('app.translations_import') }}</button>
        </form>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="group" class="form-select form-select-sm" data-auto-submit>
            @foreach(\App\Models\Language::GROUPS as $g)
                <option value="{{ $g }}" @selected($group === $g)>{{ __('app.translation_group_' . $g) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="filter" class="form-select form-select-sm" data-auto-submit>
            <option value="">{{ __('app.translations_all') }}</option>
            <option value="missing" @selected(request('filter') === 'missing')>{{ $language->is_system ? __('app.translations_original') : __('app.translations_missing') }}</option>
            <option value="done" @selected(request('filter') === 'done')>{{ $language->is_system ? __('app.translations_edited') : __('app.translations_done') }}</option>
        </select>
    </div>
    <div class="col">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('app.translations_search') }}">
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button></div>
</form>

<form method="POST" action="{{ route('admin.languages.translations.save', $language) }}">
    @csrf
    <input type="hidden" name="group" value="{{ $group }}">
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th class="w-40pct">{{ __('app.translation_original') }}</th><th>{{ __('app.translation_text') }}</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>
                            <div class="small">{{ $row->base }}</div>
                            <div class="text-muted font-monospace text-0_7rem">{{ $row->key }}</div>
                        </td>
                        <td>
                            <textarea name="t[{{ $row->key }}]" rows="{{ mb_strlen($row->base) > 90 ? 3 : 1 }}" dir="{{ $language->isRtl() ? 'rtl' : 'ltr' }}"
                                class="form-control form-control-sm {{ $row->translated ? 'border-success' : '' }}">{{ $row->value }}</textarea>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted text-center py-4">{{ __('app.no_records') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted">{{ __('app.translations_save_help') }}</span>
            <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> {{ __('app.translations_save_page') }}</button>
        </div>
    </div>
</form>
<div class="mt-3">{{ $rows->links() }}</div>
@endsection
