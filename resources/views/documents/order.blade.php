@extends("layouts.document")

{{-- A4 invoice of one order (the thermal ticket is orders/invoice). --}}
@php
    $customer = $order->customer;
    $subtotal = $order->subtotal ?? $order->total_price;
    $vatAmount = (float) ($order->vat_amount ?? 0);
    $credited = $order->creditedAmount();
    $paid = $order->payments->sum("amount");
@endphp

@section("title", __('app.invoice') . " " . $order->order_number)
@section("doc_type", __('app.invoice'))
@section("doc_meta")
    <div><strong>{{ $order->order_number }}</strong></div>
    <div>{{ __('app.date') }}: {{ $order->received_at->format(date_fmt()) }}</div>
    @if($order->delivery_date)<div>{{ __('app.delivery_date') }}: {{ $order->delivery_date->format(date_fmt()) }}</div>@endif
    <div class="muted">{{ $order->status_label }}</div>
@endsection

@section("content")
<div class="parties">
    <div>
        <div class="label">{{ __('app.bill_to') }}</div>
        <strong>{{ $customer->fiscalName() }}</strong>
        @if($customer->nuit)<div>{{ __('app.nit') }}: {{ $customer->nuit }}</div>@endif
        @if($customer->fiscalAddress())<div class="muted">{{ $customer->fiscalAddress() }}</div>@endif
        @if($customer->phone)<div class="muted">{{ $customer->phone }}</div>@endif
    </div>
    @if($order->purchase_order_ref || $order->is_credit)
        <div>
            @if($order->purchase_order_ref)<div>{{ __('app.purchase_order_ref') }}: {{ $order->purchase_order_ref }}</div>@endif
            @if($order->is_credit)
                <div class="label">{{ __('app.billed_on_credit') }}</div>
                <div>{{ __('app.due_date') }}: {{ $order->due_date?->format(date_fmt()) }}</div>
            @endif
        </div>
    @endif
</div>

<table>
    <thead>
        <tr>
            <th>{{ __('app.item') }}</th>
            <th class="r">{{ __('app.quantity') }}</th>
            <th class="r">{{ __('app.price') }}</th>
            <th class="r">{{ __('app.subtotal') }} ({{ currency_symbol() }})</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>
                    {{ $item->item_name }}
                    @if($item->promotion)<div class="muted">{{ __('app.promotion') }}: {{ $item->promotion->name }}</div>@endif
                </td>
                <td class="r">{{ $item->type === "by_weight" ? qty((float) $item->weight) . " kg" : $item->amount }}</td>
                <td class="r">{{ num($item->unit_price) }}</td>
                <td class="r">{{ num($item->subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    @if((float) $order->express_fee > 0)<tr><td>{{ __('app.express_fee') }}</td><td class="r">{{ money($order->express_fee) }}</td></tr>@endif
    @if((float) $order->delivery_fee > 0)<tr><td>{{ __('app.delivery_fee') }}</td><td class="r">{{ money($order->delivery_fee) }}</td></tr>@endif
    @if($vatAmount > 0)
        <tr><td>{{ __('app.subtotal') }}</td><td class="r">{{ money($subtotal) }}</td></tr>
        <tr><td>{{ __('app.vat') }} {{ qty((float) $order->vat_rate) }}%</td><td class="r">{{ money($vatAmount) }}</td></tr>
    @endif
    <tr class="grand"><td>{{ __('app.total') }}</td><td class="r">{{ money($order->total_price) }}</td></tr>
    @if($credited > 0)<tr><td>{{ __('app.credit_notes') }}</td><td class="r">−{{ money($credited) }}</td></tr>@endif
    @if($paid > 0 || $credited > 0)
        <tr><td>{{ __('app.paid_amount') }}</td><td class="r">{{ money($order->netPaid()) }}</td></tr>
        <tr><td><strong>{{ __('app.remaining_amount') }}</strong></td><td class="r"><strong>{{ money($order->remainingBalance()) }}</strong></td></tr>
    @endif
</table>
@endsection
