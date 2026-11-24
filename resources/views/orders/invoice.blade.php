@php
    $w = (string) request("w", "80");
    if (!in_array($w, ["58", "80"], true)) { $w = "80"; }
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.invoice') }} #{{ $order->order_number }}</title>
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

        <div class="doc-title">{{ __('app.invoice') }}</div>
        <div class="status"><span>{{ strtoupper($order->status_label) }}</span></div>

        <div class="divider"></div>

        <div class="row"><span>{{ __('app.invoice_number') }}:</span><span class="val">{{ $order->order_number }}</span></div>
        <div class="row"><span>{{ __('app.date') }}:</span><span class="val">{{ $order->received_at->format(date_fmt()) }}</span></div>
        <div class="row"><span>{{ __('app.customer') }}:</span><span class="val">{{ $order->customer->fiscalName() }}</span></div>
        @if($order->customer->nuit)<div class="row"><span>{{ __('app.nit') }}:</span><span class="val">{{ $order->customer->nuit }}</span></div>@endif
        @if($order->purchase_order_ref)<div class="row"><span>{{ __('app.purchase_order_ref') }}:</span><span class="val">{{ $order->purchase_order_ref }}</span></div>@endif

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
                        @if($item->promotion)
                            <div class="qty">{{ __('app.promotion') }}: {{ $item->promotion->name }}</div>
                        @endif
                    </td>
                    <td class="r">{{ money($item->subtotal) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        @php
            $subtotal = $order->subtotal ?? $order->total_price;
            $vatAmount = (float) ($order->vat_amount ?? 0);
        @endphp
        @php $saved = $order->items->sum(fn ($item) => $item->discountAmount()); @endphp
        @if($saved > 0)
        <div class="row"><span>{{ __('app.you_saved') }}:</span><span class="val">{{ money($saved) }}</span></div>
        @endif
        @if((float) $order->delivery_fee > 0)
        <div class="row"><span>{{ __('app.delivery_fee') }}:</span><span class="val">{{ money($order->delivery_fee) }}</span></div>
        @endif
        @if((float) $order->express_fee > 0)
        <div class="row"><span>{{ __('app.express_fee') }}:</span><span class="val">{{ money($order->express_fee) }}</span></div>
        @endif
        @if($vatAmount > 0)
        <div class="row"><span>{{ __('app.subtotal') }}:</span><span class="val">{{ money($subtotal) }}</span></div>
        <div class="row"><span>{{ __('app.vat') }} {{ qty((float) $order->vat_rate) }}%:</span><span class="val">{{ money($vatAmount) }}</span></div>
        @endif
        <div class="total">
            {{ __('app.total') }}: {{ money($order->total_price) }}
        </div>

        @php $paid = $order->payments->sum("amount"); $credited = $order->creditedAmount(); @endphp
        @if($credited > 0)
            <div class="row"><span>{{ __('app.credit_notes') }}:</span><span class="val">− {{ money($credited) }}</span></div>
        @endif
        @if($paid > 0 || $credited > 0)
            <div class="row"><span>{{ __('app.paid_amount') }}:</span><span class="val">{{ money($order->netPaid()) }}</span></div>
            <div class="row"><span>{{ __('app.remaining_amount') }}:</span><span class="val"><strong>{{ money($order->remainingBalance()) }}</strong></span></div>
        @endif
        @if($order->payments->isNotEmpty())
            <div class="divider"></div>
            <div><strong>{{ __('app.payments_received') }}</strong></div>
            @foreach($order->payments as $pm)
                <div class="row"><span>{{ $pm->paid_at->format(date_fmt()) }} · {{ __('app.' . $pm->method) }}@if($pm->transaction_ref) {{ $pm->transaction_ref }}@endif:</span><span class="val">{{ money($pm->amount) }}</span></div>
                @if($pm->change() !== null)
                    <div class="row"><span>&nbsp;&nbsp;{{ __('app.tendered') }}:</span><span class="val">{{ money($pm->tendered) }}</span></div>
                    <div class="row"><span>&nbsp;&nbsp;{{ __('app.change_due') }}:</span><span class="val">{{ money($pm->change()) }}</span></div>
                @endif
            @endforeach
        @endif
        @if($order->is_credit)
            <div class="row"><span>{{ __('app.billed_on_credit') }}:</span><span class="val">{{ __('app.due_date') }} {{ $order->due_date?->format(date_fmt()) }}</span></div>
        @endif

        <div class="divider"></div>

        <div class="footer">
            <p>@include('partials.document-footer-text')</p>
        </div>
    </div>

</body>
</html>
