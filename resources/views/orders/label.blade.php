@php
    $thermal = request("type") === "thermal";
    $labelW = $thermal ? "40" : "148";
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $thermal ? __('app.thermal_label') : __('app.print_label') }} - {{ $order->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @if($thermal)
        @@page { size: {{ $labelW }}mm auto; margin: 0; }
        @endif
        body {
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            font-size: {{ $thermal ? "8.5px" : "11px" }};
            line-height: 1.4;
            color: {{ $thermal ? "#000" : "#1a237e" }};
            background: #fff;
        }
        .label-container {
            width: {{ $thermal ? "40mm" : "148mm" }};
            @if(!$thermal) min-height: 105mm; @endif
            padding: {{ $thermal ? "1.5mm" : "8mm" }};
            margin: 0 auto;
        }
        .label-box {
            border: {{ $thermal ? "1px solid #000" : "2px solid #1a237e" }};
            border-radius: {{ $thermal ? "0" : "8px" }};
            padding: {{ $thermal ? "5px" : "12px" }};
            text-align: center;
            position: relative;
        }
        .label-box .brand {
            font-size: {{ $thermal ? "8px" : "16px" }};
            font-weight: 700;
            color: {{ $thermal ? "#000" : "#1a237e" }};
            margin-bottom: 2px;
        }
        .label-box .no-order {
            font-size: {{ $thermal ? "13px" : "22px" }};
            font-weight: 800;
            letter-spacing: 1px;
            color: {{ $thermal ? "#000" : "#1a237e" }};
            margin: {{ $thermal ? "2px 0" : "6px 0" }};
        }
        .label-box .barcode-wrapper {
            margin: {{ $thermal ? "3px auto" : "8px auto" }};
            display: flex;
            justify-content: center;
        }
        .label-box .barcode-wrapper svg {
            max-width: 100%;
            height: auto;
        }
        .label-box .barcode-text {
            font-family: "Courier New", monospace;
            font-size: {{ $thermal ? "9px" : "14px" }};
            letter-spacing: 2px;
            color: #333;
            margin-top: 2px;
        }
        @if($thermal)
        .label-box .info-row { margin-top: 4px; padding-top: 4px; border-top: 1px dashed #000; }
        .label-box .info-line { display: flex; justify-content: space-between; gap: 6px; }
        .label-box .info-line .key { text-transform: uppercase; font-size: 7.5px; color: #444; white-space: nowrap; }
        .label-box .info-line .value { font-weight: 700; font-size: 9px; text-align: right; overflow-wrap: anywhere; }
        @else
        .label-box .info-row {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #ccc;
            font-size: 10px;
        }
        .label-box .info-row .label-item {
            text-align: center;
            flex: 1;
        }
        .label-box .info-row .label-item .value {
            font-weight: 700;
            font-size: 12px;
            color: #1a237e;
        }
        .label-box .info-row .label-item .key {
            color: #6c757d;
            font-size: 8px;
            text-transform: uppercase;
        }
        @endif
        .cut-line {
            text-align: center;
            margin: 4px 0;
            color: #adb5bd;
            font-size: 8px;
        }
        .cut-line span {
            letter-spacing: 4px;
        }
        .toolbar {
            text-align: center;
            padding: 10px;
            background: #f5f7fa;
            margin-bottom: 10px;
            font-family: "Inter", Arial, sans-serif;
            font-size: 12px;
        }
        .toolbar a {
            display: inline-block;
            padding: 8px 24px;
            border-radius: 6px;
            border: 1px solid #1a237e;
            background: #1a237e;
            color: #fff;
            text-decoration: none;
            margin: 0 4px;
            font-weight: 600;
        }
        .toolbar .alt { background: #fff; color: #1a237e; }
        .toolbar .on { background: #64b5f6; border-color: #64b5f6; color: #000; }
        @@media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar, .cut-line { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a href="{{ request()->fullUrlWithQuery(["print" => 1]) }}">&#128424; {{ $thermal ? __('app.thermal_label') : __('app.print_label') }}</a>
        <a class="{{ $thermal ? "alt" : "on" }}" href="{{ url()->current() }}">{{ __('app.a5_sheet') }}</a>
        <a class="{{ $thermal ? "on" : "alt" }}" href="{{ request()->fullUrlWithQuery(["type" => "thermal", "print" => null]) }}">{{ __('app.thermal_40') }}</a>
        <a href="{{ route("orders.show", $order) }}" style="background:#6c757d;border-color:#6c757d">{{ __('app.back') }}</a>
    </div>

    <div class="label-container">
        <div class="label-box">
            <div class="brand">{{ $thermal ? "WS Clean & Laundry" : "☁ WS Clean & Laundry Serviços, Lda" }}</div>
            <div class="no-order">{{ $order->order_number }}</div>

            <div class="barcode-wrapper">
                @if(isset($barcodeSvg))
                    {!! $barcodeSvg !!}
                @else
                    <div class="barcode-text">{{ $order->order_number }}</div>
                @endif
            </div>

            @if($thermal)
                <div class="info-row">
                    <div class="info-line"><span class="key">{{ __('app.customer') }}</span><span class="value">{{ $order->customer->name }}</span></div>
                    <div class="info-line"><span class="key">{{ __('app.entry_date') }}</span><span class="value">{{ $order->received_at->format("d/m/Y") }}</span></div>
                    <div class="info-line"><span class="key">{{ __('app.qty') }}</span><span class="value">{{ $order->items->sum("amount") }} it / {{ number_format($order->items->where("type", "by_weight")->sum("weight"), 1) }} kg</span></div>
                    <div class="info-line"><span class="key">{{ __('app.total') }}</span><span class="value">{{ number_format($order->total_price, 0, ".", ",") }} Mts</span></div>
                </div>
            @else
                <div class="info-row">
                    <div class="label-item">
                        <div class="key">{{ __('app.customer') }}</div>
                        <div class="value">{{ $order->customer->name }}</div>
                    </div>
                    <div class="label-item">
                        <div class="key">{{ __('app.entry_date') }}</div>
                        <div class="value">{{ $order->received_at->format("d/m/Y") }}</div>
                    </div>
                    <div class="label-item">
                        <div class="key">{{ __('app.qty') }}</div>
                        <div class="value">{{ $order->items->sum("amount") }} item / {{ number_format($order->items->where("type", "by_weight")->sum("weight"), 1) }} kg</div>
                    </div>
                    <div class="label-item">
                        <div class="key">{{ __('app.total') }}</div>
                        <div class="value">{{ number_format($order->total_price, 0, ".", ",") }} Mts</div>
                    </div>
                </div>
            @endif
        </div>
        @if(!$thermal)
        <div class="cut-line"><span>• • • • • • • • • • • • • • • • • • • •</span></div>
        @endif
    </div>

    <script>
        @if(request("print"))
        window.addEventListener("load", function () { setTimeout(function () { window.print(); }, 250); });
        @endif
    </script>
</body>
</html>
