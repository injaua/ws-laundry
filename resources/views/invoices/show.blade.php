@extends("layouts.admin")
@section("title", __('app.invoice') . " " . $invoice->label())
@section("page_title", __('app.invoice') . " " . $invoice->label())

@php($balance = $invoice->balance())

@section("content")
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <div class="small text-muted">{{ __('app.customer') }}</div>
                        <div class="fw-bold fs-5"><a href="{{ route("receivables.customer", $invoice->customer) }}">{{ $invoice->customer->fiscalName() }}</a></div>
                        @if($invoice->customer->nuit)
                            <div class="small">{{ __('app.nit') }}: <span class="font-monospace">{{ $invoice->customer->nuit }}</span></div>
                        @endif
                    </div>
                    <span class="badge fs-6 bg-{{ $invoice->statusColor() }}">{{ __('app.invoice_status_' . $invoice->displayStatus()) }}</span>
                </div>
                <hr>
                <div class="row small g-2">
                    <div class="col-sm-4"><span class="text-muted d-block">{{ __('app.period') }}</span>{{ $invoice->period_start->format(date_fmt()) }} – {{ $invoice->period_end->format(date_fmt()) }}</div>
                    <div class="col-sm-4"><span class="text-muted d-block">{{ __('app.issue_date') }}</span>{{ $invoice->issue_date?->format(date_fmt()) ?? "-" }}</div>
                    <div class="col-sm-4"><span class="text-muted d-block">{{ __('app.due_date') }}</span>
                        <span class="{{ $invoice->isOverdue() ? "text-danger fw-semibold" : "" }}">{{ $invoice->due_date?->format(date_fmt()) ?? "-" }}</span>
                        @if($invoice->isOverdue()) <span class="badge bg-danger">{{ __('app.days_overdue', ["days" => $invoice->daysOverdue()]) }}</span> @endif
                    </div>
                </div>
                @if($invoice->notes)
                    <div class="small mt-2"><span class="text-muted">{{ __('app.notes') }}:</span> {{ $invoice->notes }}</div>
                @endif
                @if($invoice->isVoid())
                    <div class="alert alert-dark small mt-3 mb-0">{{ __('app.invoice_voided_on', ["date" => $invoice->voided_at?->format(datetime_fmt())]) }} — {{ $invoice->void_reason }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-muted small">{{ __('app.subtotal') }}</span><span>{{ money($invoice->subtotal) }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted small">{{ __('app.vat') }}</span><span>{{ money($invoice->vat_amount) }}</span></div>
                <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2"><span>{{ __('app.total') }}</span><span>{{ money($invoice->total) }}</span></div>
                @unless($invoice->isDraft() || $invoice->isVoid())
                <div class="d-flex justify-content-between text-success"><span class="small">{{ __('app.paid') }}</span><span>{{ money($invoice->paidAmount()) }}</span></div>
                <div class="d-flex justify-content-between fw-bold {{ $balance > 0 ? "text-danger" : "text-success" }}"><span>{{ __('app.outstanding_balance') }}</span><span>{{ money($balance) }}</span></div>
                @endunless

                <div class="d-grid gap-2 mt-3">
                    @if($invoice->isDraft())
                        <form action="{{ route("invoices.issue", $invoice) }}" method="POST" data-confirm="{{ __('app.invoice_issue_confirm') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send-check"></i> {{ __('app.issue_invoice') }}</button>
                        </form>
                        <a href="{{ route("invoices.print", [$invoice, "preview" => 1]) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-eye"></i> {{ __('app.preview') }}</a>
                        <form action="{{ route("invoices.void", $invoice) }}" method="POST" data-confirm="{{ __('app.invoice_discard_confirm') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> {{ __('app.discard_draft') }}</button>
                        </form>
                    @elseif($invoice->isIssued())
                        <a href="{{ route("invoices.print", $invoice) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> {{ __('app.print') }}</a>
                        @if($invoice->payments->isEmpty())
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#voidForm"><i class="bi bi-x-octagon"></i> {{ __('app.void_invoice') }}</button>
                        <form action="{{ route("invoices.void", $invoice) }}" method="POST" id="voidForm" class="collapse">
                            @csrf
                            <textarea name="void_reason" rows="2" maxlength="255" class="form-control form-control-sm mb-2 @error("void_reason") is-invalid @enderror" placeholder="{{ __('app.void_reason') }}" required></textarea>
                            @error("void_reason") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <button type="submit" class="btn btn-danger btn-sm w-100">{{ __('app.void_invoice') }}</button>
                        </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-receipt me-1"></i> {{ __('app.orders') }} ({{ $invoice->orders->count() }})</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.order_number') }}</th>
                        <th>{{ __('app.completion_date') }}</th>
                        <th>{{ __('app.purchase_order_ref') }}</th>
                        <th class="text-end">{{ __('app.total') }}</th>
                        <th class="text-end">{{ __('app.paid') }}</th>
                        <th class="text-end">{{ __('app.outstanding_balance') }}</th>
                        @if($invoice->isDraft())<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->orders as $order)
                    <tr>
                        <td class="fw-semibold"><a href="{{ route("orders.show", $order) }}">{{ $order->order_number }}</a></td>
                        <td class="small">{{ $order->completed_at?->format(date_fmt()) }}</td>
                        <td class="small">{{ $order->purchase_order_ref ?? "-" }}</td>
                        <td class="text-end">{{ num($order->netTotal()) }}</td>
                        <td class="text-end text-success">{{ num($order->netPaid()) }}</td>
                        <td class="text-end fw-semibold">{{ num(max(0, $order->remainingBalance())) }}</td>
                        @if($invoice->isDraft())
                        <td class="text-end">
                            <form action="{{ route("invoices.orders.remove", [$invoice, $order]) }}" method="POST">
                                @csrf @method("DELETE")
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="{{ __('app.remove') }}"><i class="bi bi-x-circle"></i></button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($invoice->isIssued())
<div class="row g-3">
    <div class="col-lg-5">
        @include("payments._grouped-form", [
            "action" => route("invoices.pay", $invoice),
            "outstanding" => $balance,
            "hint" => __('app.invoice_payment_hint'),
            "customer" => $invoice->customer,
        ])
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-cash-stack me-1"></i> {{ __('app.payment_history') }}</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <tbody>
                        @forelse($groups as $ref => $payments)
                        <tr>
                            <td class="small">{{ $payments->first()->paid_at->format(date_fmt()) }}</td>
                            <td class="small">{{ __('app.' . $payments->first()->method) }}</td>
                            <td class="small text-muted">{{ $payments->first()->note }}</td>
                            <td class="text-end fw-semibold">{{ money($payments->sum("amount")) }}</td>
                            <td class="text-end">
                                @if($payments->first()->group_ref)
                                <a href="{{ route("payments.group-receipt", $payments->first()->group_ref) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('app.receipt') }}"><i class="bi bi-receipt"></i></a>
                                @else
                                <a href="{{ route("payments.receipt", $payments->first()) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('app.receipt') }}"><i class="bi bi-receipt"></i></a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td class="text-center text-muted py-4">{{ __('app.no_payments_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
