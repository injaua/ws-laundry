@extends("layouts.admin")
@section("title", $deposit->label())
@section("page_title", __('app.bank_deposit') . " " . $deposit->label())
@section("page_subtitle", $deposit->deposited_at->format(date_fmt()) . ($deposit->slip_number ? ' · ' . $deposit->slip_number : ''))

@section("content")
@include("treasury._tabs")
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.date') }}</span><span>{{ $deposit->deposited_at->format(date_fmt()) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.deposit_slip') }}</span><span class="font-monospace">{{ $deposit->slip_number ?? '—' }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.cash') }}</span><span>{{ money($deposit->cash_amount) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.cheques') }}</span><span>{{ money($deposit->cheques_amount) }}</span></li>
                <li class="list-group-item d-flex justify-content-between fw-bold"><span>{{ __('app.total') }}</span><span>{{ money($deposit->total) }}</span></li>
                <li class="list-group-item small">{{ __('app.status') }}: @if($deposit->confirmed_at)<span class="badge bg-success">{{ __('app.deposit_confirmed') }}</span> {{ $deposit->confirmed_at->format(date_fmt()) }}@else<span class="badge bg-secondary">{{ __('app.deposit_unconfirmed') }}</span>@endif</li>
                <li class="list-group-item small text-muted">{{ $deposit->user?->name }} · {{ $deposit->created_at->format(datetime_fmt()) }}@if($deposit->note)<div>{{ $deposit->note }}</div>@endif</li>
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">{{ __('app.cheques') }}</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('app.cheque') }}</th><th>{{ __('app.customer') }}</th><th class="text-end">{{ __('app.amount') }}</th><th>{{ __('app.status') }}</th></tr></thead>
                    <tbody>
                    @forelse($deposit->cheques as $cheque)
                        <tr>
                            <td>{{ $cheque->label() }}</td>
                            <td>{{ $cheque->customer?->name }}</td>
                            <td class="text-end">{{ num($cheque->amount) }}</td>
                            <td><span class="badge bg-{{ $cheque->statusColor() }}">{{ __('app.cheque_status_' . $cheque->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">{{ __('app.deposit_cash_only') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
