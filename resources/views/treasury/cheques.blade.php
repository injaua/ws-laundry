@extends("layouts.admin")
@section("title", __('app.cheques'))
@section("page_title", __('app.treasury'))
@section("page_subtitle", __('app.treasury_subtitle'))

@section("content")
@include("treasury._tabs")

<div class="row g-2 mb-3">
    @foreach(\App\Models\Cheque::STATUSES as $s)
        <div class="col-6 col-md-3">
            <a href="{{ route('treasury.cheques', ['status' => $s]) }}" class="card text-decoration-none border-{{ $status === $s ? \App\Models\Cheque::colorFor($s) : 'light' }}">
                <div class="card-body py-2">
                    <div class="small text-muted">{{ __('app.cheque_status_' . $s) }}</div>
                    <div class="fs-5 fw-bold text-{{ \App\Models\Cheque::colorFor($s) }}">{{ money($totals[$s]->total ?? 0) }}</div>
                    <div class="small text-muted">{{ $totals[$s]->n ?? 0 }} {{ __('app.cheques') }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
    <form method="GET" class="d-flex gap-2">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('app.cheque_search') }}">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
        <a href="{{ route('treasury.cheques', ['status' => 'all']) }}" class="btn btn-link">{{ __('app.all') }}</a>
    </form>
    @if($status === 'in_hand')
        <a href="{{ route('treasury.deposits.create') }}" class="btn btn-primary"><i class="bi bi-bank"></i> {{ __('app.new_bank_deposit') }}</a>
    @endif
</div>

<form method="POST" action="{{ route('treasury.cheques.clear-many') }}" id="clear-many">@csrf</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                @if($status === 'deposited')<th></th>@endif
                <th>{{ __('app.cheque') }}</th><th>{{ __('app.customer') }}</th><th>{{ __('app.orders') }}</th><th>{{ __('app.cheque_date') }}</th>
                <th class="text-end">{{ __('app.amount') }}</th><th>{{ __('app.status') }}</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($cheques as $cheque)
                @php($orders = $cheque->status === 'bounced' ? ($bouncedPayments[$cheque->id] ?? collect())->pluck('order') : $cheque->payments->pluck('order'))
                <tr>
                    @if($status === 'deposited')<td><input type="checkbox" class="form-check-input" name="cheque_ids[]" value="{{ $cheque->id }}" form="clear-many" aria-label="{{ $cheque->label() }}"></td>@endif
                    <td><span class="fw-semibold">{{ $cheque->label() }}</span>@if($cheque->drawer)<div class="small text-muted">{{ $cheque->drawer }}</div>@endif</td>
                    <td>{{ $cheque->customer?->name }}</td>
                    <td class="small">@foreach($orders->filter() as $o)<a href="{{ route('orders.show', $o->id) }}">{{ $o->order_number }}</a>@if(!$loop->last), @endif @endforeach</td>
                    <td class="small">
                        {{ $cheque->cheque_date->format(date_fmt()) }}
                        @if($cheque->isInHand() && $cheque->isPostDated())<span class="badge bg-warning text-dark">{{ __('app.post_dated') }}</span>@endif
                    </td>
                    <td class="text-end">{{ num($cheque->amount) }}</td>
                    <td>
                        <span class="badge bg-{{ $cheque->statusColor() }}">{{ __('app.cheque_status_' . $cheque->status) }}</span>
                        @if($cheque->deposit)<div class="small"><a href="{{ route('treasury.deposits.show', $cheque->deposit) }}">{{ $cheque->deposit->label() }}</a></div>@endif
                        @if($cheque->status === 'bounced')<div class="small text-danger">{{ $cheque->bounced_at?->format(date_fmt()) }} — {{ $cheque->bounce_reason }}</div>@endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if($cheque->status === 'deposited')
                            <form method="POST" action="{{ route('treasury.cheques.clear', $cheque) }}" class="d-inline">@csrf
                                <button class="btn btn-sm btn-outline-success" title="{{ __('app.cheque_mark_cleared') }}"><i class="bi bi-check2"></i></button>
                            </form>
                        @endif
                        @if($cheque->canBounce())
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#bounce{{ $cheque->id }}" title="{{ __('app.cheque_mark_bounced') }}"><i class="bi bi-arrow-return-left"></i></button>
                        @endif
                    </td>
                </tr>
                @if($cheque->canBounce())
                <tr class="collapse" id="bounce{{ $cheque->id }}">
                    <td colspan="8" class="bg-danger-subtle">
                        <form method="POST" action="{{ route('treasury.cheques.bounce', $cheque) }}" class="row g-2 align-items-end" data-confirm="{{ __('app.cheque_bounce_confirm') }}">
                            @csrf
                            <div class="col-md-3"><label class="form-label small mb-0">{{ __('app.date') }}</label><input type="date" name="bounced_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" required></div>
                            <div class="col-md-6"><label class="form-label small mb-0">{{ __('app.cheque_bounce_reason') }}</label><input type="text" name="reason" maxlength="255" class="form-control form-control-sm" placeholder="{{ __('app.cheque_bounce_reason_placeholder') }}" required></div>
                            <div class="col-md-3"><button class="btn btn-sm btn-danger w-100">{{ __('app.cheque_mark_bounced') }}</button></div>
                            <div class="col-12 small text-muted">{{ __('app.cheque_bounce_help') }}</div>
                        </form>
                    </td>
                </tr>
                @endif
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">{{ __('app.no_cheques') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($status === 'deposited' && $cheques->isNotEmpty())
        <div class="card-footer"><button class="btn btn-sm btn-success" form="clear-many"><i class="bi bi-check2-all"></i> {{ __('app.cheque_mark_selected_cleared') }}</button></div>
    @endif
</div>
<div class="mt-3">{{ $cheques->links() }}</div>
@endsection
