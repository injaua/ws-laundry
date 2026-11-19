@extends("layouts.document")

{{-- A4 receipt of one payment (the thermal ticket is payments/receipt). --}}
@php
    $order = $payment->order;
    $customer = $order->customer;
    $number = "REC-" . str_pad($payment->id, 6, "0", STR_PAD_LEFT);
    $paidUpToThis = \App\Support\Money::sum($order->payments->where("id", "<=", $payment->id)->pluck("amount"));
    $remaining = \App\Support\Money::sub($order->total_price, $paidUpToThis);
    [$netPaid, $vatPaid] = $order->splitGrossAmount((float) $payment->amount);
@endphp

@section("title", __('app.receipt') . " " . $number)
@section("doc_type", __('app.receipt'))
@section("doc_meta")
    <div><strong>{{ $number }}</strong></div>
    <div>{{ __('app.date') }}: {{ $payment->paid_at->format(date_fmt()) }}</div>
    <div>{{ __('app.order_number') }}: {{ $order->order_number }}</div>
@endsection
@section("no_bank_details", "1")

@section("content")
<div class="parties">
    <div>
        <div class="label">{{ __('app.received_from') }}</div>
        <strong>{{ $customer->fiscalName() }}</strong>
        @if($customer->nuit)<div>{{ __('app.nit') }}: {{ $customer->nuit }}</div>@endif
        @if($customer->fiscalAddress())<div class="muted">{{ $customer->fiscalAddress() }}</div>@endif
    </div>
    <div>
        <div class="label">{{ __('app.amount_received') }}</div>
        <div class="text-big-bold">{{ money($payment->amount) }}</div>
        <div>{{ __('app.payment_method') }}: {{ __('app.' . $payment->method) }}</div>
        @if($payment->cheque)
            <div class="muted">{{ __('app.cheque') }}: {{ $payment->cheque->label() }}</div>
        @elseif($payment->transaction_ref)
            <div class="muted">{{ __('app.reference') }}: {{ $payment->transaction_ref }}</div>
        @endif
    </div>
</div>

<table class="totals">
    @if($vatPaid > 0)
        <tr><td>{{ __('app.base_amount') }}</td><td class="r">{{ money($netPaid) }}</td></tr>
        <tr><td>{{ __('app.vat') }} {{ qty((float) $order->vat_rate) }}%</td><td class="r">{{ money($vatPaid) }}</td></tr>
    @endif
    <tr class="grand"><td>{{ __('app.amount_received') }}</td><td class="r">{{ money($payment->amount) }}</td></tr>
    @if($payment->change() !== null)
        <tr><td>{{ __('app.tendered') }}</td><td class="r">{{ money($payment->tendered) }}</td></tr>
        <tr><td>{{ __('app.change_due') }}</td><td class="r">{{ money($payment->change()) }}</td></tr>
    @endif
</table>

<table>
    <thead>
        <tr>
            <th>{{ __('app.order_number') }}</th>
            <th>{{ __('app.date') }}</th>
            <th class="r">{{ __('app.total') }}</th>
            <th class="r">{{ __('app.paid_amount') }}</th>
            <th class="r">{{ __('app.remaining_amount') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $order->order_number }}</td>
            <td>{{ $order->received_at->format(date_fmt()) }}</td>
            <td class="r">{{ num($order->total_price) }}</td>
            <td class="r">{{ num($paidUpToThis) }}</td>
            <td class="r">{{ $remaining > 0 ? num($remaining) : __('app.paid') }}</td>
        </tr>
    </tbody>
</table>

@if($payment->cheque)
    <div class="note">{{ __('app.cheque_subject_to_clearing') }}</div>
@endif
@if($payment->note)
    <div class="note"><div class="label">{{ __('app.notes') }}</div>{{ $payment->note }}</div>
@endif
@endsection
