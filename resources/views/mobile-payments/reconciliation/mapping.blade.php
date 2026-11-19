@extends("layouts.admin")
@section("title", __('app.statement_mapping'))
@section("page_title", __('app.statement_mapping'))
@section("page_subtitle", $upload['name'])

@section("content")
<form method="POST" action="{{ route('wallet-reconciliation.map', $token) }}" class="card mw-640px">
    @csrf
    <div class="card-body">
        <p class="small text-muted">{{ __('app.statement_mapping_help') }}</p>
        @foreach(['ref' => true, 'amount' => true, 'date' => false, 'phone' => false] as $field => $required)
            <div class="row g-2 align-items-center mb-2">
                <label class="col-5 form-label mb-0">{{ __('app.statement_col_' . $field) }} @if($required)<span class="text-danger">*</span>@endif</label>
                <div class="col-7">
                    <select name="columns[{{ $field }}]" class="form-select form-select-sm @error('columns.' . $field) is-invalid @enderror" @if($required) required @endif>
                        <option value="">—</option>
                        @foreach($upload['headers'] as $i => $header)
                            <option value="{{ $i }}" @selected(old('columns.' . $field) !== null && (int) old('columns.' . $field) === $i)>{{ $header !== '' ? $header : __('app.column') . ' ' . ($i + 1) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endforeach
    </div>
    <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('wallet-reconciliation.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
        <button class="btn btn-primary"><i class="bi bi-check2-all"></i> {{ __('app.statement_import_button') }}</button>
    </div>
</form>
@endsection
