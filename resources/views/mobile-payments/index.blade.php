@extends("layouts.admin")
@section("title", __('app.mobile_payments'))
@section("page_title", __('app.mobile_payments'))
@section("page_subtitle", __('app.mobile_payments_subtitle'))

@section("content")
@foreach($modes as $provider => $mode)
    @if($mode === 'simulation')
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> {{ __('app.mm_simulation_banner', ['provider' => __('app.' . $provider)]) }}</div>
    @endif
@endforeach

<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
        <select name="provider" class="form-select">
            <option value="">{{ __('app.all_operators') }}</option>
            @foreach(\App\Models\MobileTransaction::PROVIDERS as $p)
                <option value="{{ $p }}" @selected(request('provider') === $p)>{{ __('app.' . $p) }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select">
            <option value="">{{ __('app.all_statuses') }}</option>
            @foreach(\App\Models\MobileTransaction::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ __('app.mm_status_' . $s) }}</option>
            @endforeach
        </select>
        <select name="type" class="form-select">
            <option value="">{{ __('app.mm_all_types') }}</option>
            @foreach(\App\Models\MobileTransaction::TYPES as $t)
                <option value="{{ $t }}" @selected(request('type') === $t)>{{ __('app.mm_type_' . $t) }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary"><i class="bi bi-funnel"></i></button>
    </form>
    @if(auth()->user()->hasRole(['admin', 'owner']))
        <div class="d-flex gap-2">
            <a href="{{ route('wallet-reconciliation.index') }}" class="btn btn-outline-secondary"><i class="bi bi-check2-all"></i> {{ __('app.wallet_reconciliation') }}</a>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.mobile-money.edit') }}" class="btn btn-outline-secondary"><i class="bi bi-gear"></i> {{ __('app.settings') }}</a>
            @endif
        </div>
    @endif
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">{{ __('app.mm_received') }}</div><div class="fs-5 fw-bold text-success">{{ money($received) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">{{ __('app.mm_refunded') }}</div><div class="fs-5 fw-bold">{{ money($refunded) }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">{{ __('app.mm_open') }}</div><div class="fs-5 fw-bold text-primary">{{ $open }}</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">{{ __('app.mm_failed_or_expired') }}</div><div class="fs-5 fw-bold text-danger">{{ $failed }}</div></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>{{ __('app.date') }}</th><th>{{ __('app.mm_type') }}</th><th>{{ __('app.mm_operator') }}</th><th>{{ __('app.order') }}</th>
                <th>{{ __('app.phone') }}</th><th class="text-end">{{ __('app.amount') }}</th><th>{{ __('app.transaction_ref') }}</th><th>{{ __('app.mm_channel') }}</th><th>{{ __('app.status') }}</th>
            </tr></thead>
            <tbody>
            @forelse($transactions as $tx)
                <tr>
                    <td class="small"><a href="{{ route('mobile-payments.show', $tx) }}">{{ $tx->created_at->format('d/m H:i') }}</a></td>
                    <td class="small">{{ __('app.mm_type_' . $tx->type) }}</td>
                    <td>{{ __('app.' . $tx->provider) }}</td>
                    <td>@if($tx->order)<a href="{{ route('orders.show', $tx->order_id) }}">{{ $tx->order->order_number }}</a>@endif <span class="small text-muted">{{ $tx->customer?->name }}</span></td>
                    <td class="small">+{{ $tx->phone }}</td>
                    <td class="text-end">{{ num($tx->amount) }}</td>
                    <td class="small font-monospace">{{ $tx->provider_ref ?? '—' }}</td>
                    <td class="small">{{ __('app.mm_channel_' . $tx->channel) }}@if($tx->user) · {{ $tx->user->name }}@endif</td>
                    <td><span class="badge bg-{{ $tx->statusColor() }}" title="{{ $tx->response_message }}">{{ __('app.mm_status_' . $tx->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">{{ __('app.mm_no_transactions') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $transactions->links() }}</div>
@endsection
