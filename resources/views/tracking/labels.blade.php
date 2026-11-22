<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.piece_labels') }} - {{ $order->order_number }}</title>
    @vite(["resources/css/piece-tags.css", "resources/js/print.js"])
</head>
<body class="{{ $thermal ? "tags-thermal" : "tags-a4" }}">
    <div class="toolbar">
        {{ __('app.piece_labels') }} · {{ $order->order_number }} · {{ $pieces->count() }} {{ __('app.pieces') }}
        <button type="button" data-print>{{ __('app.print') }}</button>
        @if($thermal)
            <a href="{{ request()->fullUrlWithQuery(["type" => null]) }}">A4</a>
        @else
            <a href="{{ request()->fullUrlWithQuery(["type" => "thermal"]) }}">{{ __('app.thermal_label') }}</a>
        @endif
    </div>

    <div class="sheet">
        @forelse($pieces as $piece)
            <div class="tag">
                <div class="shop">{{ \App\Support\SystemSettings::companyName() }}</div>
                <div class="barcode">
                    @if($barcodes[$piece->id])
                        {!! $barcodes[$piece->id] !!}
                    @endif
                </div>
                <div class="code">{{ $piece->code }}</div>
                <div class="desc">{{ $piece->description }}{{ $piece->color ? " · " . $piece->color : "" }}</div>
                <div class="cust"><span class="seq">{{ $piece->seq }}/{{ $total }}</span> · {{ $order->customer?->name }}</div>
            </div>
        @empty
            <p class="empty">{{ __('app.no_pieces_yet') }}</p>
        @endforelse
    </div>
</body>
</html>
