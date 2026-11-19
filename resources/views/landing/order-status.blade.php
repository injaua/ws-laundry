<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('app.check_status_title') }} · {{ \App\Support\SystemSettings::companyName() }}</title>
    <meta name="description" content="{{ __('app.enter_order_number_desc') }}">
    @include("landing.partials.head")
</head>
<body>

@include("landing.partials.nav")

{{-- Floating WA --}}
@if ($settings["whatsapp_number"] ?? null)
<a href="https://wa.me/{{ $settings["whatsapp_number"] }}" class="floating-wa" id="floatingWa" target="_blank" rel="noopener" aria-label="WhatsApp">
    <i class="bi bi-whatsapp"></i>
</a>
@endif

{{-- Hero + search --}}
<section class="os-hero {{ $order || $notFound ? "compact" : "" }}">
    <div class="container">
        <span class="hero-badge"><i class="bi bi-geo-alt"></i> {{ __('app.where_is_my_laundry') }}</span>
        <h1>{{ __('app.check_status_title') }}</h1>
        <p>{{ __('app.enter_order_number_desc') }}</p>
        <form action="{{ route("orders.check-status") }}" method="GET" class="search-card">
            <span class="text-soft"><i class="bi bi-search"></i></span>
            <input type="text" name="order_number" placeholder="{{ __('app.example_order_no') }}" value="{{ request("order_number") }}" aria-label="{{ __('app.order_number') }}" required @if(!$order && !$notFound)autofocus @endif>
            <input type="text" name="phone_last4" class="phone-last4" placeholder="{{ __('app.phone_last4_placeholder') }}" value="{{ request("phone_last4") }}" aria-label="{{ __('app.phone_last4_label') }}" inputmode="numeric" pattern="\d{4}" maxlength="4" autocomplete="off" required>
            <button class="btn-pill btn-solid" type="submit">{{ __('app.search') }}</button>
        </form>
        <div class="search-hint">{{ __('app.example_order_hint', ['number' => 'ORD260718001']) }}</div>
    </div>
</section>

{{-- Result --}}
@if($order || $notFound)
<div class="os-body" @if($order || $notFound) data-scroll-on-load @endif>
    <div class="container">
        @if($order)
            @php
                $isCompleted = $order->status == 'completed';
                $isProgress = in_array($order->status, ['washing','drying','ironing','folding']);
                $bgColor = $isCompleted ? '#d1fae5' : ($isProgress ? '#dbeafe' : '#fef3c7');
                $textColor = $isCompleted ? '#065f46' : ($isProgress ? '#1e40af' : '#92400e');
                $steps = \App\Models\Order::STATUSES;
                $statusIdx = array_search($order->status, $steps);
                $labels = [
                    __('app.status_received'), __('app.status_washing'), __('app.status_drying'), __('app.status_ironing'),
                    __('app.status_folding'), __('app.status_ready'), __('app.status_delivering'), __('app.status_completed'),
                ];
                $icons = ['inbox','water','wind','thermometer-half','box-seam','check2','truck','check-circle'];
                $totalPaid = $order->netPaid();
                $remaining = $order->remainingBalance();
            @endphp
            <div class="status-card">
                <div class="head">
                    <div>
                        <h3><i class="bi bi-receipt me-2 text-accent"></i>{{ $order->order_number }}</h3>
                        <div class="text-soft small"><i class="bi bi-person me-1"></i>{{ $order->customer->name }}</div>
                    </div>
                    <span class="status-badge" style="background:{{ $bgColor }};color:{{ $textColor }}">
                        <i class="bi bi-{{ $isCompleted ? "check-circle" : "hourglass-split" }}"></i> {{ $order->status_label }}
                    </span>
                </div>

                <div class="row g-3 mb-2">
                    <div class="col-sm-6">
                        <div class="eyebrow">{{ __('app.entry_date') }}</div>
                        <div class="fw-semibold">{{ $order->received_at->isoFormat("D MMMM Y") }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="eyebrow">{{ __('app.completion_date') }}</div>
                        <div class="fw-semibold">{{ $order->completed_at?->isoFormat("D MMMM Y") ?? __('app.not_determined') }}</div>
                    </div>
                    @if($order->pieces->isNotEmpty() && !$isCompleted)
                    @php($piecesReady = $order->pieces->filter->isReady()->count())
                    <div class="col-12">
                        <div class="eyebrow">{{ __('app.piece_tracking') }}</div>
                        <div class="fw-semibold">{{ __('app.pieces_ready_of', ["ready" => $piecesReady, "total" => $order->pieces->count()]) }}</div>
                        <div class="progress mt-1 h-6px" role="progressbar" aria-label="{{ __('app.pieces_ready_of', ["ready" => $piecesReady, "total" => $order->pieces->count()]) }}" aria-valuenow="{{ $piecesReady }}" aria-valuemin="0" aria-valuemax="{{ $order->pieces->count() }}">
                            <div class="progress-bar" style="width:{{ round($piecesReady / $order->pieces->count() * 100) }}%;background:var(--accent)"></div>
                        </div>
                    </div>
                    @endif
                    @if($order->notes)
                    <div class="col-12">
                        <div class="eyebrow">{{ __('app.notes') }}</div>
                        <div>{{ $order->notes }}</div>
                    </div>
                    @endif
                </div>

                <div class="stepper">
                    <div class="bar" style="width:{{ $statusIdx > 0 ? round($statusIdx / (count($steps) - 1) * 88) : 0 }}%"></div>
                    @foreach($steps as $i => $s)
                        <div class="st {{ $i < $statusIdx ? "done" : ($i == $statusIdx ? "current" : "") }}">
                            <div class="ic"><i class="bi bi-{{ $icons[$i] }}"></i></div>
                            <small>{{ $labels[$i] }}</small>
                        </div>
                    @endforeach
                </div>

                <div class="row g-3">
                    @if($order->items->count())
                    <div class="col-lg-7">
                        <div class="os-panel h-100">
                            <div class="panel-title">{{ __('app.service_items') }}</div>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>{{ __('app.service') }}</th><th>{{ __('app.type') }}</th><th class="text-end">{{ __('app.price') }}</th><th class="text-end">{{ __('app.subtotal') }}</th></tr></thead>
                                    <tbody>
                                        @foreach($order->items as $item)
                                        <tr>
                                            <td class="fw-semibold">{{ $item->item_name }}</td>
                                            <td>
                                                @if($item->type == 'by_weight')
                                                    <span class="svc-badge weight">{{ $item->weight }} kg</span>
                                                @elseif($item->type == 'by_package')
                                                    <span class="svc-badge piece">{{ $item->amount }}× {{ __('app.by_package') }}</span>
                                                @else
                                                    <span class="svc-badge piece">{{ $item->amount }} {{ __('app.pieces') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ money($item->unit_price) }}</td>
                                            <td class="text-end fw-semibold">{{ money($item->subtotal) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot><tr><td colspan="3" class="text-end fw-bold">{{ __('app.total') }}</td><td class="text-end fw-bold text-navy">{{ money($order->total_price) }}</td></tr></tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="col-lg-{{ $order->items->count() ? 5 : 12 }}">
                        <div class="os-panel h-100">
                            <div class="panel-title">{{ __('app.payment') }}</div>
                            <div class="pay-row"><span>{{ __('app.total') }}</span><strong>{{ money($order->total_price) }}</strong></div>
                            <div class="pay-row"><span>{{ __('app.paid_amount') }}</span><strong class="text-wa-dark">{{ money($totalPaid) }}</strong></div>
                            <hr class="border-line">
                            @if($remaining <= 0)
                                <span class="status-badge pill-ok"><i class="bi bi-check-circle"></i> {{ __('app.paid') }}</span>
                            @else
                                <span class="status-badge pill-wait"><i class="bi bi-hourglass-split"></i> {{ __('app.remaining') }} {{ money($remaining) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @elseif($notFound)
            <div class="status-card os-empty">
                <i class="bi bi-search-heart"></i>
                <h3>{{ __('app.order_not_found') }}</h3>
                <p>{{ __('app.order_not_found_desc', ['number' => $orderNumber]) }}</p>
                <a href="{{ route("home") }}" class="btn-pill btn-solid"><i class="bi bi-house"></i> {{ __('app.back') }}</a>
            </div>
        @endif
    </div>
</div>
@endif

{{-- CTA --}}
<section class="lp-cta pt-hero">
    <div class="container">
        <div class="cta-band">
            <h2>{{ __('app.no_account_yet') }}</h2>
            <p>{{ __('app.register_prompt_desc') }}</p>
            @if ($settings["whatsapp_number"] ?? null)
                <a href="https://wa.me/{{ $settings["whatsapp_number"] }}" class="btn-pill btn-wa" target="_blank" rel="noopener"><i class="bi bi-whatsapp fs-5"></i> {{ __('app.contact_us') }}</a>
            @else
                <a href="{{ route('register') }}" class="btn-pill btn-wa"><i class="bi bi-person-plus fs-5"></i> {{ __('app.register') }}</a>
            @endif
        </div>
    </div>
</section>


@include("landing.partials.footer")
@include("landing.partials.scripts")
</body>
</html>
