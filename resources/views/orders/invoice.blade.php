@php
    $w = (string) request("w", "80");
    if (!in_array($w, ["58", "80"], true)) { $w = "80"; }
    $fs = $w === "58" ? "9.5px" : "11.5px";
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.invoice') }} #{{ $order->order_number }}</title>
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
        table { width: 100%; border-collapse: collapse; margin-top: 2px; }
        th { border-bottom: 1px solid #000; text-align: left; font-size: 0.95em; padding: 2px 0; }
        th.r, td.r { text-align: right; }
        td { padding: 2px 0; vertical-align: top; }
        td .qty { font-size: 0.9em; }
        .total { text-align: right; font-weight: bold; font-size: 1.25em; margin-top: 4px; }
        .footer { text-align: center; font-size: 0.85em; margin-top: 8px; }
        .status { text-align: center; margin: 6px 0; }
        .status span { border: 1px solid #000; padding: 2px 10px; font-weight: bold; letter-spacing: 1px; }
        .toolbar { position: sticky; top: 0; background: #f5f7fa; padding: 8px; text-align: center; font-family: Arial, sans-serif; font-size: 12px; }
        .toolbar a { margin: 0 3px; padding: 6px 14px; border-radius: 6px; border: 1px solid #1a237e; background: #1a237e; color: #fff; text-decoration: none; display: inline-block; font-size: 12px; }
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

        <div class="doc-title">{{ __('app.invoice') }}</div>
        <div class="status"><span>{{ strtoupper($order->status_label) }}</span></div>

        <div class="divider"></div>

        <div class="row"><span>{{ __('app.invoice_number') }}:</span><span class="val">{{ $order->order_number }}</span></div>
        <div class="row"><span>{{ __('app.date') }}:</span><span class="val">{{ $order->received_at->format("d/m/Y") }}</span></div>
        <div class="row"><span>{{ __('app.customer') }}:</span><span class="val">{{ $order->customer->name }}</span></div>

        <div class="divider"></div>

        <table>
            <thead>
                <tr><th>{{ __('app.item') }}</th><th class="r">{{ __('app.price') }}</th></tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>
                        {{ $item->item_name }}
                        <span class="qty">
                            @if($item->type == "by_weight")
                                ({{ $item->weight }} kg)
                            @elseif($item->type == "by_package")
                                ({{ $item->amount }}x)
                            @else
                                ({{ $item->amount }}x)
                            @endif
                        </span>
                    </td>
                    <td class="r">{{ number_format($item->subtotal, 0, ".", ",") }} Mts</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        @php
            $subtotal = $order->subtotal ?? $order->total_price;
            $ivaAmount = (float) ($order->iva_amount ?? 0);
        @endphp
        @if($ivaAmount > 0)
        <div class="row"><span>{{ __('app.subtotal') }}:</span><span class="val">{{ number_format($subtotal, 0, ".", ",") }} Mts</span></div>
        <div class="row"><span>{{ __('app.iva') }} {{ rtrim(rtrim(number_format((float) $order->iva_rate, 2, ".", ","), "0"), ".") }}%:</span><span class="val">{{ number_format($ivaAmount, 0, ".", ",") }} Mts</span></div>
        @endif
        <div class="total">
            {{ __('app.total') }}: {{ number_format($order->total_price, 0, ".", ",") }} Mts
        </div>

        @php $paid = $order->payments->sum("amount"); @endphp
        @if($paid > 0)
            <div class="row"><span>{{ __('app.paid_amount') }}:</span><span class="val">{{ number_format($paid, 0, ".", ",") }} Mts</span></div>
            <div class="row"><span>{{ __('app.remaining_amount') }}:</span><span class="val"><strong>{{ number_format($order->total_price - $paid, 0, ".", ",") }} Mts</strong></span></div>
        @endif

        <div class="divider"></div>

        <div class="footer">
            <p>Obrigado pela sua preferência! / Thank you!</p>
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
