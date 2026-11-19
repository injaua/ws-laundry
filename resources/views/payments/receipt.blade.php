@php
    $w = (string) request("w", "80");
    if (!in_array($w, ["58", "80"], true)) { $w = "80"; }
    $paidUpToThis = $payment->order->payments->where("id", "<=", $payment->id)->sum("amount");
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.thermal_receipt') }} REC-{{ str_pad($payment->id, 6, "0", STR_PAD_LEFT) }}</title>
    @vite(["resources/css/ticket.css", "resources/js/print.js"])
</head>
<body class="ticket-{{ $w }}" data-thermal-width="80" @if(request("print")) data-auto-print @endif>
    <div class="toolbar">
        <a href="{{ request()->fullUrlWithQuery(["w" => $w, "print" => 1]) }}">&#128424; {{ __('app.print') }}</a>
        <a href="{{ request()->fullUrlWithQuery(["w" => "58", "print" => null]) }}" data-remember="thermalWidth=58"
           class="{{ $w === "58" ? "on" : "alt" }}">58 mm</a>
        <a href="{{ request()->fullUrlWithQuery(["w" => "80", "print" => null]) }}" data-remember="thermalWidth=80"
           class="{{ $w === "80" ? "on" : "alt" }}">80 mm</a>
    </div>

    <div class="ticket">
        <div class="header">
            @include('partials.company-ticket-header')
        </div>

        <div class="doc-title">{{ __('app.thermal_receipt') }}</div>

        <div class="divider"></div>

        <div class="row"><span>{{ __('app.receipt_number') }}:</span><span class="val">REC-{{ str_pad($payment->id, 6, "0", STR_PAD_LEFT) }}</span></div>
        <div class="row"><span>{{ __('app.date') }}:</span><span class="val">{{ $payment->paid_at->format(date_fmt()) }}</span></div>
        <div class="row"><span>{{ __('app.order_number') }}:</span><span class="val">{{ $payment->order->order_number }}</span></div>
        <div class="row"><span>{{ __('app.customer') }}:</span><span class="val">{{ $payment->order->customer->fiscalName() }}</span></div>
        @if($payment->order->customer->nuit)<div class="row"><span>{{ __('app.nit') }}:</span><span class="val">{{ $payment->order->customer->nuit }}</span></div>@endif

        <div class="divider"></div>

        <div class="row"><span>{{ __('app.payment_method') }}:</span><span class="val">{{ __('app.' . $payment->method) }}</span></div>
        @if($payment->cheque)
            <div class="row"><span>{{ __('app.cheque') }}:</span><span class="val">{{ $payment->cheque->label() }}</span></div>
            @if($payment->cheque->isPostDated())<div class="row"><span>{{ __('app.cheque_date') }}:</span><span class="val">{{ $payment->cheque->cheque_date->format(date_fmt()) }}</span></div>@endif
        @elseif($payment->transaction_ref)
            <div class="row"><span>{{ __('app.reference') }}:</span><span class="val">{{ $payment->transaction_ref }}</span></div>
        @endif
        <div class="amount">{{ money($payment->amount) }}</div>
        @if($payment->change() !== null)
            <div class="row"><span>{{ __('app.tendered') }}:</span><span class="val">{{ money($payment->tendered) }}</span></div>
            <div class="row"><span>{{ __('app.change_due') }}:</span><span class="val">{{ money($payment->change()) }}</span></div>
        @endif
        @if($payment->cheque)
            <div class="note">{{ __('app.cheque_subject_to_clearing') }}</div>
        @endif
        @php
            [$netPaid, $vatPaid] = $payment->order->splitGrossAmount((float) $payment->amount);
        @endphp
        @if($vatPaid > 0)
        <div class="row"><span>{{ __('app.base_amount') }}:</span><span class="val">{{ money($netPaid) }}</span></div>
        <div class="row"><span>{{ __('app.vat') }} {{ qty((float) $payment->order->vat_rate) }}%:</span><span class="val">{{ money($vatPaid) }}</span></div>
        @endif
        @if($payment->note)
            <div class="note">{{ __('app.notes') }}: {{ $payment->note }}</div>
        @endif

        <div class="divider"></div>

        <div class="row"><span>{{ __('app.total') }}:</span><span class="val">{{ money($payment->order->total_price) }}</span></div>
        <div class="row"><span>{{ __('app.paid_amount') }}:</span><span class="val">{{ money($paidUpToThis) }}</span></div>
        @php $remaining = $payment->order->total_price - $paidUpToThis; @endphp
        <div class="row"><span>{{ __('app.remaining_amount') }}:</span><span class="val">@if($remaining > 0){{ money($remaining) }}@else {{ __('app.paid') }} @endif</span></div>
        @if($payment->order->is_credit && $remaining > 0)
            <div class="row"><span>{{ __('app.due_date') }}:</span><span class="val">{{ $payment->order->due_date?->format(date_fmt()) }}</span></div>
        @endif

        <div class="divider"></div>

        <div class="footer">
            <p>@include('partials.document-footer-text')</p>
            <p>{{ $payment->created_at->format(datetime_fmt()) }}</p>
        </div>
    </div>

</body>
</html>
