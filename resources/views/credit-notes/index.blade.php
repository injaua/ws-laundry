@extends("layouts.admin")
@section("title", __('app.credit_notes'))
@section("page_title", __('app.credit_notes'))

@section("content")
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">{{ __('app.refunds') }}</div>
            <div class="fs-4 fw-bold text-danger">{{ money($totals["refund"]) }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">{{ __('app.cn_deductions') }}</div>
            <div class="fs-4 fw-bold">{{ money($totals["deduct"]) }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">{{ __('app.cn_vat_regularised') }}</div>
            <div class="fs-4 fw-bold">{{ money($totals["vat"]) }}</div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label small mb-0" for="cnSearch">{{ __('app.search') }}</label>
                <input type="text" id="cnSearch" name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="{{ __('app.cn_search_placeholder') }}">
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small mb-0" for="cnType">{{ __('app.type') }}</label>
                <select id="cnType" name="type" class="form-select form-select-sm">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="refund" @selected($type === "refund")>{{ __('app.refunds') }}</option>
                    <option value="deduct" @selected($type === "deduct")>{{ __('app.cn_deductions') }}</option>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small mb-0" for="cnFrom">{{ __('app.from') }}</label>
                <input type="date" id="cnFrom" name="from" class="form-control form-control-sm" value="{{ $from->toDateString() }}">
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small mb-0" for="cnTo">{{ __('app.to') }}</label>
                <input type="date" id="cnTo" name="to" class="form-control form-control-sm" value="{{ $to->toDateString() }}">
            </div>
            <div class="col-md-2 col-6 d-grid">
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> {{ __('app.filter') }}</button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.number') }}</th>
                    <th>{{ __('app.date') }}</th>
                    <th>{{ __('app.transactions') }}</th>
                    <th>{{ __('app.customer') }}</th>
                    <th>{{ __('app.reason') }}</th>
                    <th>{{ __('app.type') }}</th>
                    <th class="text-end">{{ __('app.total') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($notes as $n)
                <tr class="{{ $n->isVoided() ? "text-decoration-line-through text-muted" : "" }}">
                    <td class="text-nowrap font-monospace small">{{ $n->number }}</td>
                    <td class="text-nowrap">{{ $n->issued_at->format(date_fmt()) }}</td>
                    <td><a href="{{ route("orders.show", $n->order_id) }}">{{ $n->order?->order_number }}</a></td>
                    <td>{{ $n->customer?->name }}</td>
                    <td class="small">{{ __('app.cn_reason_' . $n->reason) }}</td>
                    <td>
                        @if($n->isVoided())
                            <span class="badge bg-secondary">{{ __('app.cn_voided_stamp') }}</span>
                        @elseif($n->isRefund())
                            <span class="badge bg-danger">{{ __('app.refund') }} · {{ __('app.' . $n->refund_method) }}</span>
                        @else
                            <span class="badge bg-info text-dark">{{ __('app.cn_settlement_deduct') }}</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap fw-semibold">{{ money($n->amount) }}</td>
                    <td class="text-end"><a href="{{ route("credit-notes.show", $n) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1" title="{{ __('app.print') }}" aria-label="{{ __('app.print') }} {{ $n->number }}"><i class="bi bi-printer"></i></a></td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">{{ __('app.cn_none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @if($notes->hasPages())<div class="card-footer bg-transparent">{{ $notes->links() }}</div>@endif
</div>
<p class="small text-muted mt-2">{{ __('app.cn_index_help') }}</p>
@endsection
