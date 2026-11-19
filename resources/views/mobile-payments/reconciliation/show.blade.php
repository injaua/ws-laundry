@extends("layouts.admin")
@section("title", __('app.wallet_reconciliation'))
@section("page_title", __('app.statement') . " " . __('app.' . $statement->provider))
@section("page_subtitle", $statement->file_name . ($statement->period_from ? ' · ' . $statement->period_from->format(date_fmt()) . ' – ' . $statement->period_to->format(date_fmt()) : ''))

@php($counts = $lines->countBy('status'))

@section("content")
<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('wallet-reconciliation.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.wallet_reconciliation') }}</a>
    <span class="small text-muted align-self-center">{{ $statement->file_name }}@if($statement->period_from) · {{ $statement->period_from->format(date_fmt()) }} – {{ $statement->period_to->format(date_fmt()) }}@endif</span>
</div>

<div class="row g-2 mb-3">
    @foreach(['matched' => 'success', 'amount_mismatch' => 'danger', 'unmatched' => 'warning'] as $status => $color)
        <div class="col-6 col-md-3"><div class="card border-{{ $color }}"><div class="card-body py-2">
            <div class="small text-muted">{{ __('app.statement_status_' . $status) }}</div>
            <div class="fs-5 fw-bold text-{{ $color }}">{{ $counts[$status] ?? 0 }}</div>
        </div></div></div>
    @endforeach
    <div class="col-6 col-md-3"><div class="card border-danger"><div class="card-body py-2">
        <div class="small text-muted">{{ __('app.statement_missing_payments') }}</div>
        <div class="fs-5 fw-bold text-danger">{{ $missing->count() }}</div>
    </div></div></div>
</div>

@if($missing->isNotEmpty())
<div class="card border-danger mb-3">
    <div class="card-header text-danger"><i class="bi bi-exclamation-octagon me-1"></i> {{ __('app.statement_missing_payments') }}</div>
    <div class="card-body small text-muted pb-0">{{ __('app.statement_missing_help') }}</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('app.date') }}</th><th>{{ __('app.order') }}</th><th>{{ __('app.customer') }}</th><th>{{ __('app.transaction_ref') }}</th><th>{{ __('app.cashier') }}</th><th class="text-end">{{ __('app.amount') }}</th></tr></thead>
            <tbody>
            @foreach($missing as $p)
                <tr>
                    <td>{{ $p->paid_at->format(date_fmt()) }}</td>
                    <td>@if($p->order)<a href="{{ route('orders.show', $p->order) }}">{{ $p->order->order_number }}</a>@endif</td>
                    <td>{{ $p->order?->customer?->name }}</td>
                    <td class="font-monospace">{{ $p->transaction_ref ?? '—' }}</td>
                    <td>{{ $p->user?->name }}</td>
                    <td class="text-end">{{ num($p->amount) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">{{ __('app.statement_lines') }}</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>{{ __('app.date') }}</th><th>{{ __('app.transaction_ref') }}</th><th>{{ __('app.phone') }}</th><th class="text-end">{{ __('app.statement_amount') }}</th><th class="text-end">{{ __('app.statement_recorded') }}</th><th>{{ __('app.status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($lines as $line)
                <tr>
                    <td class="small">{{ $line->occurred_at?->format(datetime_fmt()) ?? '—' }}</td>
                    <td class="font-monospace small">{{ $line->transaction_ref }}</td>
                    <td class="small">{{ $line->phone ?? '—' }}</td>
                    <td class="text-end">{{ num($line->amount) }}</td>
                    <td class="text-end">{{ $line->recorded_amount !== null ? num($line->recorded_amount) : '—' }}</td>
                    <td><span class="badge bg-{{ $line->statusColor() }}">{{ __('app.statement_status_' . $line->status) }}</span>@if($line->match_note)<div class="small text-muted">{{ $line->match_note }}</div>@endif</td>
                    <td class="text-end">
                        @if(in_array($line->status, ['unmatched', 'ignored'], true))
                            <form method="POST" action="{{ route('wallet-reconciliation.ignore', $line) }}">@csrf
                                <button class="btn btn-sm btn-link p-0" title="{{ __('app.statement_ignore_help') }}">{{ $line->status === 'ignored' ? __('app.statement_unignore') : __('app.statement_ignore') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('app.statement_no_lines') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">{{ __('app.statement_lines_help') }}</div>
</div>
@endsection
