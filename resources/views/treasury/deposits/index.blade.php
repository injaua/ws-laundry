@extends("layouts.admin")
@section("title", __('app.bank_deposits'))
@section("page_title", __('app.treasury'))
@section("page_subtitle", __('app.treasury_subtitle'))

@section("content")
@include("treasury._tabs")
<div class="d-flex justify-content-end mb-2">
    <a href="{{ route('treasury.deposits.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('app.new_bank_deposit') }}</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>{{ __('app.date') }}</th><th>{{ __('app.deposit_slip') }}</th><th class="text-end">{{ __('app.cash') }}</th><th class="text-end">{{ __('app.cheques') }}</th><th class="text-end">{{ __('app.total') }}</th><th>{{ __('app.status') }}</th><th>{{ __('app.user') }}</th></tr></thead>
            <tbody>
            @forelse($deposits as $d)
                <tr>
                    <td><a href="{{ route('treasury.deposits.show', $d) }}">{{ $d->deposited_at->format(date_fmt()) }}</a> <span class="small text-muted">{{ $d->label() }}</span></td>
                    <td class="font-monospace small">{{ $d->slip_number ?? '—' }}</td>
                    <td class="text-end">{{ num($d->cash_amount) }}</td>
                    <td class="text-end">{{ num($d->cheques_amount) }} <span class="small text-muted">({{ $d->cheques_count }})</span></td>
                    <td class="text-end fw-semibold">{{ num($d->total) }}</td>
                    <td>@if($d->confirmed_at)<span class="badge bg-success">{{ __('app.deposit_confirmed') }}</span>@else<span class="badge bg-secondary">{{ __('app.deposit_unconfirmed') }}</span>@endif</td>
                    <td class="small">{{ $d->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('app.no_bank_deposits') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $deposits->links() }}</div>
@endsection
