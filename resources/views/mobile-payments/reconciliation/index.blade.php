@extends("layouts.admin")
@section("title", __('app.wallet_reconciliation'))
@section("page_title", __('app.wallet_reconciliation'))
@section("page_subtitle", __('app.wallet_reconciliation_subtitle'))

@section("content")
<div class="row g-3">
    <div class="col-lg-4">
        <form method="POST" action="{{ route('wallet-reconciliation.store') }}" enctype="multipart/form-data" class="card mb-3">
            @csrf
            <div class="card-header"><i class="bi bi-upload me-1"></i> {{ __('app.statement_import') }}</div>
            <div class="card-body">
                <label class="form-label">{{ __('app.mm_operator') }}</label>
                <select name="provider" class="form-select mb-2" required>
                    @foreach(\App\Services\MobileMoney\WalletReconciliation::PROVIDERS as $m)
                        <option value="{{ $m }}" @selected(old('provider') === $m)>{{ $m === 'transfer' ? __('app.bank_statement') : __('app.' . $m) }}</option>
                    @endforeach
                </select>
                <label class="form-label">{{ __('app.statement_file') }}</label>
                <input type="file" name="file" accept=".csv,.txt,.xlsx,.xls" class="form-control @error('file') is-invalid @enderror" required>
                @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">{{ __('app.statement_import_help') }}</div>
                <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check2-all"></i> {{ __('app.statement_import_button') }}</button>
            </div>
        </form>

        <div class="card">
            <div class="card-header">{{ __('app.not_yet_reconciled') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($pending as $method => $row)
                    <li class="list-group-item d-flex justify-content-between small">
                        <span>{{ $method === 'transfer' ? __('app.transfers') : __('app.' . $method) }}</span>
                        <span>{{ $row['count'] }} · {{ money($row['amount']) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">{{ __('app.imported_statements') }}</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('app.date') }}</th><th>{{ __('app.mm_operator') }}</th><th>{{ __('app.statement_file') }}</th><th>{{ __('app.period') }}</th><th class="text-end">{{ __('app.statement_lines') }}</th><th class="text-end">{{ __('app.statement_matched') }}</th></tr></thead>
                    <tbody>
                    @forelse($statements as $s)
                        <tr>
                            <td class="small"><a href="{{ route('wallet-reconciliation.show', $s) }}">{{ $s->created_at->format(datetime_fmt()) }}</a><div class="text-muted">{{ $s->user?->name }}</div></td>
                            <td>{{ __('app.' . $s->provider) }}</td>
                            <td class="small text-truncate mw-200px">{{ $s->file_name }}</td>
                            <td class="small">{{ $s->period_from?->format('d/m') }} – {{ $s->period_to?->format(date_fmt()) }}</td>
                            <td class="text-end">{{ $s->lines_count }}</td>
                            <td class="text-end"><span class="{{ $s->matched_count < $s->lines_count ? 'text-warning fw-semibold' : 'text-success' }}">{{ $s->matched_count }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ __('app.no_statements') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $statements->links() }}</div>
    </div>
</div>
@endsection
