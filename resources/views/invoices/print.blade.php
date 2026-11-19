@extends("layouts.document")

@php($customer = $invoice->customer)
@section("title", __('app.invoice') . " " . $invoice->label())
@section("doc_type", $invoice->isDraft() ? __('app.invoice_status_draft') : __('app.invoice'))
@section("doc_meta")
    <div><strong>{{ $invoice->label() }}</strong></div>
    <div>{{ __('app.issue_date') }}: {{ $invoice->issue_date?->format(date_fmt()) ?? "-" }}</div>
    <div>{{ __('app.due_date') }}: {{ $invoice->due_date?->format(date_fmt()) ?? "-" }}</div>
    <div>{{ __('app.period') }}: {{ $invoice->period_start->format(date_fmt()) }} – {{ $invoice->period_end->format(date_fmt()) }}</div>
    @if($invoice->isVoid())<div class="stamp text-loss">{{ strtoupper(__('app.invoice_status_void')) }}</div>@endif
    @if($invoice->isPaid())<div class="stamp text-gain">{{ strtoupper(__('app.invoice_status_paid')) }}</div>@endif
@endsection

@section("content")
<div class="parties">
    <div>
        <div class="label">{{ __('app.bill_to') }}</div>
        <strong>{{ $customer->fiscalName() }}</strong>
        @if($customer->nuit)<div>{{ __('app.nit') }}: {{ $customer->nuit }}</div>@endif
        @if($customer->fiscalAddress())<div class="muted">{{ $customer->fiscalAddress() }}</div>@endif
        @if($customer->contact_person)<div class="muted">{{ __('app.contact_person') }}: {{ $customer->contact_person }}</div>@endif
    </div>
    <div>
        <div class="label">{{ __('app.payment_terms') }}</div>
        <div>{{ __('app.payment_terms_days', ["days" => $customer->credit_terms_days]) }}</div>
        <div class="muted">{{ $invoice->orders->count() }} {{ __('app.orders') }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>{{ __('app.order_number') }}</th>
            <th>{{ __('app.completion_date') }}</th>
            <th>{{ __('app.purchase_order_ref') }}</th>
            <th>{{ __('app.description') }}</th>
            <th class="r">{{ __('app.vat') }}</th>
            <th class="r">{{ __('app.total') }} ({{ currency_symbol() }})</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->orders as $order)
        <tr>
            <td>{{ $order->order_number }}</td>
            <td>{{ $order->completed_at?->format(date_fmt()) }}</td>
            <td>{{ $order->purchase_order_ref ?? "-" }}</td>
            <td>
                @foreach($order->items as $item)
                    <div>
                        {{ $item->service?->name ?? $item->package?->name ?? "-" }}
                        <span class="muted">· {{ $item->type === "by_weight" ? qty((float) $item->weight) . " kg" : $item->amount . "×" }} @ {{ num($item->unit_price) }}</span>
                    </div>
                @endforeach
                @if($order->creditedAmount() > 0)
                    <div class="muted">{{ __('app.credit_notes') }}: −{{ num($order->creditedAmount()) }}</div>
                @endif
            </td>
            <td class="r">{{ (float) $order->vat_rate > 0 ? qty($order->vat_rate) . "%" : "-" }}</td>
            <td class="r">{{ num($order->netTotal()) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>{{ __('app.subtotal') }}</td><td class="r">{{ money($invoice->subtotal) }}</td></tr>
    <tr><td>{{ __('app.vat') }}</td><td class="r">{{ money($invoice->vat_amount) }}</td></tr>
    <tr class="grand"><td>{{ __('app.total') }}</td><td class="r">{{ money($invoice->total) }}</td></tr>
    @if($invoice->isIssued() && $invoice->paidAmount() > 0)
        <tr><td>{{ __('app.paid') }}</td><td class="r">−{{ money($invoice->paidAmount()) }}</td></tr>
        <tr><td><strong>{{ __('app.outstanding_balance') }}</strong></td><td class="r"><strong>{{ money($invoice->balance()) }}</strong></td></tr>
    @endif
</table>

@if($invoice->notes)
    <div class="note"><div class="label">{{ __('app.notes') }}</div>{{ $invoice->notes }}</div>
@endif
@endsection
