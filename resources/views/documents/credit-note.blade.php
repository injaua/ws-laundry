@extends("layouts.document")

{{-- A4 credit note (the thermal ticket is credit-notes/show). --}}
@php
    $order = $note->order;
    $customer = $order->customer;
@endphp

@section("title", __('app.credit_note') . " " . $note->number)
@section("doc_type", __('app.credit_note'))
@section("doc_meta")
    <div><strong>{{ $note->number }}</strong></div>
    <div>{{ __('app.date') }}: {{ $note->issued_at->format(date_fmt()) }}</div>
    <div>{{ __('app.cn_refers_to') }}: {{ $order->order_number }} ({{ $order->received_at->format(date_fmt()) }})</div>
    @if($note->isVoided())<div class="stamp text-loss">{{ mb_strtoupper(__('app.cn_voided_stamp')) }}</div>@endif
@endsection
@section("no_bank_details", "1")

@section("content")
<div class="parties">
    <div>
        <div class="label">{{ __('app.customer') }}</div>
        <strong>{{ $customer?->fiscalName() }}</strong>
        @if($customer?->nuit)<div>{{ __('app.nit') }}: {{ $customer->nuit }}</div>@endif
        @if($customer?->fiscalAddress())<div class="muted">{{ $customer->fiscalAddress() }}</div>@endif
    </div>
    <div>
        <div class="label">{{ __('app.reason') }}</div>
        <div>{{ __('app.cn_reason_' . $note->reason) }}</div>
        <div class="muted">
            @if($note->isRefund())
                {{ __('app.cn_settlement_refund') }}: {{ __('app.' . $note->refund_method) }}
            @else
                {{ __('app.cn_settlement_deduct') }}
            @endif
        </div>
    </div>
</div>

<table>
    <thead>
        <tr><th>{{ __('app.description') }}</th><th class="r">{{ __('app.total') }} ({{ currency_symbol() }})</th></tr>
    </thead>
    <tbody>
        <tr><td>{{ $note->description }}</td><td class="r">{{ num($note->amount) }}</td></tr>
    </tbody>
</table>

<table class="totals">
    @if((float) $note->vat_amount > 0)
        <tr><td>{{ __('app.subtotal') }}</td><td class="r">{{ money($note->net_amount) }}</td></tr>
        <tr><td>{{ __('app.vat') }} {{ qty((float) $note->vat_rate) }}%</td><td class="r">{{ money($note->vat_amount) }}</td></tr>
    @endif
    <tr class="grand"><td>{{ __('app.total') }}</td><td class="r">{{ money($note->amount) }}</td></tr>
</table>

<div class="muted">{{ __('app.cn_issued_by') }}: {{ $note->user?->name }}</div>
@if($note->isVoided())
    <div class="note"><div class="label">{{ __('app.cn_voided_on') }} {{ $note->voided_at->format(datetime_fmt()) }}</div>{{ $note->void_reason }}</div>
@endif
<div class="note">{{ __('app.cn_footer') }}</div>

@if($note->isRefund())
    <table class="signatures">
        <tr><td></td><td><div class="line">{{ __('app.cn_customer_signature') }}</div></td></tr>
    </table>
@endif
@endsection
