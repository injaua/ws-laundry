@php
    $w = (string) request("w", "80");
    if (!in_array($w, ["58", "80"], true)) { $w = "80"; }
    $fs = $w === "58" ? "9.5px" : "11.5px";
    $paidUpToThis = $payment->order->payments->where("id", "<=", $payment->id)->sum("amount");
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.thermal_receipt') }} REC-{{ str_pad($payment->id, 6, "0", STR_PAD_LEFT) }}</title>
    <style>
        @@page { size: {{ $w }}mm auto; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Courier New", monospace; font-size: {{ $fs }}; color: #000; background: #fff; }
        .ticket { width: {{ $w }}mm; margin: 0 auto; padding: 8px 5px 12px; }
        .header { text-align: center; margin-bottom: 6px; }
        .header h2 { font-size: 1.35em; }
        .header p { font-size: 0.9em; }
        .doc-title { text-align: center; font-weight: bold; font-size: 1.15em; letter-spacing: 2px; margin: 4px 0; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; padding: 1px 0; }
        .row span:first-child { white-space: nowrap; }
        .row .val { text-align: right; overflow-wrap: anywhere; }
        .amount { text-align: center; font-weight: bold; font-size: 1.6em; margin: 6px 0; }
        .note { font-size: 0.9em; margin-top: 3px; overflow-wrap: anywhere; }
        .footer { text-align: center; font-size: 0.85em; margin-top: 8px; }
        .toolbar { position: sticky; top: 0; background: #f5f7fa; padding: 8px; text-align: center; font-family: Arial, sans-serif; font-size: 12px; }
        .toolbar a, .toolbar button { margin: 0 3px; padding: 6px 14px; border-radius: 6px; border: 1px solid #1a237e; background: #1a237e; color: #fff; text-decoration: none; cursor: pointer; font-size: 12px; }
        .toolbar .alt { background: #fff; color: #1a237e; }
        .toolbar .on { background: #64b5f6; border-color: #64b5f6; color: #000; font-weight: bold; }
        @@media print { .toolbar { display: none; } .ticket { width: 100%; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ request()->fullUrlWithQuery(["w" => $w, "print" => 1]) }}">&#128424; {{ __('app.print') }}</a>
        <a href="{{ request()->fullUrlWithQuery(["w" => "58", "print" => null]) }}" onclick="localStorage.setItem('thermalWidth','58')"
           class="{{ $w === "58" ? "on" : "alt" }}">58 mm</a>
        <a href="{{ request()->fullUrlWithQuery(["w" => "80", "print" => null]) }}" onclick="localStorage.setItem('thermalWidth','80')"
           class="{{ $w === "80" ? "on" : "alt" }}">80 mm</a>
    </div>

    <div class="ticket">
        <div class="header">
            <h2>WS Clean &amp; Laundry Serviços, Lda</h2>
            <p>Lavandaria Profissional</p>
        </div>

        <div class="doc-title">{{ __('app.thermal_receipt') }}</div>

        <div class="divider"></div>

        <div class="row"><span>{{ __('app.receipt_number') }}:</span><span class="val">REC-{{ str_pad($payment->id, 6, "0", STR_PAD_LEFT) }}</span></div>
        <div class="row"><span>{{ __('app.date') }}:</span><span class="val">{{ $payment->paid_at->format("d/m/Y") }}</span></div>
        <div class="row"><span>{{ __('app.order_number') }}:</span><span class="val">{{ $payment->order->order_number }}</span></div>
        <div class="row"><span>{{ __('app.customer') }}:</span><span class="val">{{ $payment->order->customer->name }}</span></div>

        <div class="divider"></div>

        <div class="row"><span>{{ __('app.payment_method') }}:</span><span class="val">{{ __('app.' . $payment->method) }}</span></div>
        <div class="amount">{{ number_format($payment->amount, 0, ".", ",") }} Mts</div>
        @php
            [$netPaid, $vatPaid] = $payment->order->splitGrossAmount((float) $payment->amount);
        @endphp
        @if($vatPaid > 0)
        <div class="row"><span>{{ __('app.base_amount') }}:</span><span class="val">{{ number_format($netPaid, 0, ".", ",") }} Mts</span></div>
        <div class="row"><span>{{ __('app.iva') }} {{ rtrim(rtrim(number_format((float) $payment->order->iva_rate, 2, ".", ","), "0"), ".") }}%:</span><span class="val">{{ number_format($vatPaid, 0, ".", ",") }} Mts</span></div>
        @endif
        @if($payment->note)
            <div class="note">{{ __('app.notes') }}: {{ $payment->note }}</div>
        @endif

        <div class="divider"></div>

        <div class="row"><span>{{ __('app.total') }}:</span><span class="val">{{ number_format($payment->order->total_price, 0, ".", ",") }} Mts</span></div>
        <div class="row"><span>{{ __('app.paid_amount') }}:</span><span class="val">{{ number_format($paidUpToThis, 0, ".", ",") }} Mts</span></div>
        @php $remaining = $payment->order->total_price - $paidUpToThis; @endphp
        <div class="row"><span>{{ __('app.remaining_amount') }}:</span><span class="val">@if($remaining > 0){{ number_format($remaining, 0, ".", ",") }} Mts@else {{ __('app.paid') }} @endif</span></div>

        <div class="divider"></div>

        <div class="footer">
            <p>Obrigado pela sua preferência! / Thank you!</p>
            <p>{{ $payment->created_at->format("d/m/Y H:i") }}</p>
        </div>
    </div>

    <script>
        (function () {
            var url = new URL(location.href);
            var saved = localStorage.getItem("thermalWidth");
            if (!url.searchParams.get("w") && saved && saved !== "80") {
                url.searchParams.set("w", saved);
                location.replace(url.toString());
                return;
            }
            @if(request("print"))
            window.addEventListener("load", function () { setTimeout(function () { window.print(); }, 250); });
            @endif
        })();
    </script>
</body>
</html>
