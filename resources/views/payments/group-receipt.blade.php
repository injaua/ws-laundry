@extends("layouts.document")

@php($first = $payments->first())

@section("title", __('app.receipt') . " " . $groupRef)
@section("doc_type", __('app.receipt'))
@section("doc_meta")
    <div><strong>{{ $groupRef }}</strong></div>
    <div>{{ __('app.payment_date') }}: {{ $first->paid_at->format(date_fmt()) }}</div>
    <div>{{ __('app.payment_method') }}: {{ __('app.' . $first->method) }}</div>
@endsection

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
        <div class="text-big-bold">{{ money($payments->sum("amount")) }}</div>
        @if($first->note)<div class="muted">{{ __('app.payment_reference') }}: {{ $first->note }}</div>@endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>{{ __('app.order_number') }}</th>
            <th>{{ __('app.invoice') }}</th>
            <th class="r">{{ __('app.amount') }} ({{ currency_symbol() }})</th>
            <th class="r">{{ __('app.outstanding_balance') }} ({{ currency_symbol() }})</th>
        </tr>
    </thead>
    <tbody>
        @foreach($payments as $payment)
        <tr>
            <td>{{ $payment->order->order_number }}</td>
            <td>{{ $payment->invoice?->number ?? "-" }}</td>
            <td class="r">{{ num($payment->amount) }}</td>
            <td class="r">{{ num(max(0, $payment->order->remainingBalance())) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr class="grand"><td>{{ __('app.total') }}</td><td class="r">{{ money($payments->sum("amount")) }}</td></tr>
</table>

<p class="muted">{{ __('app.received_by') }}: {{ $first->user?->name ?? "-" }}</p>
@endsection
