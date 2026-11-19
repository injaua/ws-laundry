@php
    $w = (string) request("w", "80");
    if (!in_array($w, ["58", "80"], true)) { $w = "80"; }
    $order = $note->order;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.credit_note') }} {{ $note->number }}</title>
    @vite(["resources/css/ticket.css", "resources/js/print.js"])
</head>
<body class="ticket-{{ $w }}" @if(request("print")) data-auto-print @endif>
    <div class="toolbar">
        <a href="{{ request()->fullUrlWithQuery(["w" => $w, "print" => 1]) }}">&#128424; {{ __('app.print') }}</a>
        <a href="{{ request()->fullUrlWithQuery(["w" => "58", "print" => null]) }}" class="{{ $w === "58" ? "on" : "alt" }}">58 mm</a>
        <a href="{{ request()->fullUrlWithQuery(["w" => "80", "print" => null]) }}" class="{{ $w === "80" ? "on" : "alt" }}">80 mm</a>
        <a href="{{ route("orders.show", $note->order_id) }}" class="alt">{{ __('app.back') }}</a>
    </div>

    <div class="ticket">
        <div class="header">
            @include('partials.company-ticket-header')
        </div>

        <div class="doc-title">{{ mb_strtoupper(__('app.credit_note')) }}</div>
        @if($note->isVoided())
            <div class="void">{{ mb_strtoupper(__('app.cn_voided_stamp')) }}</div>
        @endif

        <div class="divider"></div>
        <div class="row"><span>{{ __('app.number') }}:</span><span class="val">{{ $note->number }}</span></div>
        <div class="row"><span>{{ __('app.date') }}:</span><span class="val">{{ $note->issued_at->format(date_fmt()) }}</span></div>
        <div class="row"><span>{{ __('app.cn_refers_to') }}:</span><span class="val">{{ $order->order_number }} ({{ $order->received_at->format(date_fmt()) }})</span></div>
        <div class="row"><span>{{ __('app.customer') }}:</span><span class="val">{{ $order->customer?->name }}</span></div>

        <div class="divider"></div>
        <div class="row"><span>{{ __('app.reason') }}:</span><span class="val">{{ __('app.cn_reason_' . $note->reason) }}</span></div>
        <div class="text">{{ $note->description }}</div>

        <div class="divider"></div>
        @if((float) $note->vat_amount > 0)
            <div class="row"><span>{{ __('app.subtotal') }}:</span><span class="val">{{ money($note->net_amount) }}</span></div>
            <div class="row"><span>{{ __('app.vat') }} {{ qty((float) $note->vat_rate) }}%:</span><span class="val">{{ money($note->vat_amount) }}</span></div>
        @endif
        <div class="total">{{ __('app.total') }}: {{ money($note->amount) }}</div>

        <div class="divider"></div>
        @if($note->isRefund())
            <div class="row"><span>{{ __('app.cn_settlement_refund') }}:</span><span class="val">{{ __('app.' . $note->refund_method) }}</span></div>
        @else
            <div class="row"><span>{{ __('app.cn_settlement_deduct') }}</span><span class="val"></span></div>
        @endif
        <div class="row"><span>{{ __('app.cn_issued_by') }}:</span><span class="val">{{ $note->user?->name }}</span></div>
        @if($note->isVoided())
            <div class="row"><span>{{ __('app.cn_voided_on') }}:</span><span class="val">{{ $note->voided_at->format(datetime_fmt()) }}</span></div>
            <div class="text">{{ $note->void_reason }}</div>
        @endif

        @if($note->isRefund())
            <div class="sign">{{ __('app.cn_customer_signature') }}</div>
        @endif

        <div class="footer"><p>{{ __('app.cn_footer') }}</p><p>@include('partials.document-footer-text')</p></div>
    </div>

</body>
</html>
