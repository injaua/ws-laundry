@php
    $thermal = request("type") === "thermal";
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $thermal ? __('app.thermal_label') : __('app.print_label') }} - {{ $order->order_number }}</title>
    @vite(["resources/css/label.css", "resources/js/print.js"])
</head>
<body class="{{ $thermal ? "label-thermal" : "label-a4" }}" @if(request("print")) data-auto-print @endif>
    <div class="toolbar">
        <a href="{{ request()->fullUrlWithQuery(["print" => 1]) }}">&#128424; {{ $thermal ? __('app.thermal_label') : __('app.print_label') }}</a>
        <a class="{{ $thermal ? "alt" : "on" }}" href="{{ url()->current() }}">{{ __('app.a5_sheet') }}</a>
        <a class="{{ $thermal ? "on" : "alt" }}" href="{{ request()->fullUrlWithQuery(["type" => "thermal", "print" => null]) }}">{{ __('app.thermal_40') }}</a>
        <a class="muted" href="{{ route("orders.show", $order) }}">{{ __('app.back') }}</a>
    </div>

    <div class="label-container">
        <div class="label-box">
            <div class="brand">{{ $thermal ? \App\Support\SystemSettings::companyName() : "☁ " . \App\Support\SystemSettings::legalName() }}</div>
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
                    <div class="info-line"><span class="key">{{ __('app.entry_date') }}</span><span class="value">{{ $order->received_at->format(date_fmt()) }}</span></div>
                    <div class="info-line"><span class="key">{{ __('app.qty') }}</span><span class="value">{{ $order->items->sum("amount") }} it / {{ number_format($order->items->where("type", "by_weight")->sum("weight"), 1) }} kg</span></div>
                    <div class="info-line"><span class="key">{{ __('app.total') }}</span><span class="value">{{ money($order->total_price) }}</span></div>
                </div>
            @else
                <div class="info-row">
                    <div class="label-item">
                        <div class="key">{{ __('app.customer') }}</div>
                        <div class="value">{{ $order->customer->name }}</div>
                    </div>
                    <div class="label-item">
                        <div class="key">{{ __('app.entry_date') }}</div>
                        <div class="value">{{ $order->received_at->format(date_fmt()) }}</div>
                    </div>
                    <div class="label-item">
                        <div class="key">{{ __('app.qty') }}</div>
                        <div class="value">{{ $order->items->sum("amount") }} item / {{ number_format($order->items->where("type", "by_weight")->sum("weight"), 1) }} kg</div>
                    </div>
                    <div class="label-item">
                        <div class="key">{{ __('app.total') }}</div>
                        <div class="value">{{ money($order->total_price) }}</div>
                    </div>
                </div>
            @endif
        </div>
        @if(!$thermal)
        <div class="cut-line"><span>• • • • • • • • • • • • • • • • • • • •</span></div>
        @endif
    </div>

</body>
</html>
