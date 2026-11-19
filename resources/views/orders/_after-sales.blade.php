{{-- After-sales: complaints, credit notes / refunds and the "never collected" record --}}
@php
    $canIssueCn = auth()->user()->can("create", \App\Models\CreditNote::class);
    $cnStatusColors = ["open" => "danger", "in_progress" => "warning", "resolved" => "success", "rejected" => "secondary"];
@endphp
<div class="card mt-3" id="after-sales">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span><i class="bi bi-life-preserver me-1"></i> {{ __('app.after_sales') }}</span>
        <span class="d-flex gap-1">
            @can("update", $order)
            <a href="{{ route("complaints.create", ["order" => $order->id]) }}" class="btn btn-sm btn-outline-warning text-nowrap"><i class="bi bi-chat-square-text"></i> {{ __('app.complaint_new') }}</a>
            @endcan
            @if($canIssueCn && !$order->trashed())
            <a href="{{ route("orders.credit-notes.create", $order) }}" class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-file-earmark-minus"></i> {{ __('app.cn_new') }}</a>
            @endif
        </span>
    </div>
    <div class="card-body">
        @if($order->abandoned_at)
            <div class="alert alert-secondary py-2 small">
                <i class="bi bi-box-seam"></i> {{ __('app.unclaimed_order_abandoned', ["date" => $order->abandoned_at->format(date_fmt()), "destination" => __('app.abandon_' . $order->abandon_destination)]) }}
                @if($order->abandon_note) — {{ $order->abandon_note }}@endif
            </div>
        @endif

        @if($order->complaints->isEmpty() && $order->creditNotes->isEmpty())
            <p class="text-muted small mb-0">{{ __('app.after_sales_none') }}</p>
        @endif

        @if($order->complaints->isNotEmpty())
            <h6 class="small text-uppercase text-muted">{{ __('app.complaints') }}</h6>
            <ul class="list-unstyled small mb-3">
                @foreach($order->complaints as $c)
                    <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
                        <span><a href="{{ route("complaints.show", $c) }}" class="font-monospace">{{ $c->number }}</a> · {{ __('app.complaint_category_' . $c->category) }}</span>
                        <span class="badge bg-{{ $cnStatusColors[$c->status] }} align-self-center">{{ __('app.complaint_status_' . $c->status) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if($order->creditNotes->isNotEmpty())
            <h6 class="small text-uppercase text-muted">{{ __('app.credit_notes') }}</h6>
            @foreach($order->creditNotes as $n)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-1 border-bottom small {{ $n->isVoided() ? "text-muted" : "" }}">
                    <span class="{{ $n->isVoided() ? "text-decoration-line-through" : "" }}">
                        <span class="font-monospace">{{ $n->number }}</span> · {{ $n->issued_at->format(date_fmt()) }} ·
                        {{ $n->isRefund() ? __('app.refund') . " (" . __('app.' . $n->refund_method) . ")" : __('app.cn_settlement_deduct') }}
                    </span>
                    <span class="d-flex align-items-center gap-1">
                        <span class="fw-semibold">{{ money((float) $n->amount) }}</span>
                        @if($n->isVoided())<span class="badge bg-secondary">{{ __('app.cn_voided_stamp') }}</span>@endif
                        <a href="{{ route("credit-notes.show", $n) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1" title="{{ __('app.print') }}" aria-label="{{ __('app.print') }} {{ $n->number }}"><i class="bi bi-printer"></i></a>
                        @can("void", $n)
                        <button class="btn btn-sm btn-outline-danger py-0 px-1" data-bs-toggle="collapse" data-bs-target="#voidCn{{ $n->id }}" title="{{ __('app.cn_void') }}" aria-label="{{ __('app.cn_void') }} {{ $n->number }}"><i class="bi bi-x-circle"></i></button>
                        @endcan
                    </span>
                    <div class="w-100 text-muted">{{ __('app.cn_reason_' . $n->reason) }} — {{ $n->description }}@if($n->isVoided()) · {{ __('app.cn_voided_on') }} {{ $n->voided_at->format(date_fmt()) }}: {{ $n->void_reason }}@endif</div>
                    {{-- Refund straight to the customer's wallet (B2C) --}}
                    @if($n->isRefund() && !$n->isVoided() && in_array($n->refund_method, \App\Models\MobileTransaction::PROVIDERS, true) && \App\Services\MobileMoney\MobileMoneySettings::enabled($n->refund_method))
                        @php($refundTx = $n->mobileRefund)
                        @if($refundTx && in_array($refundTx->status, ['pending', 'processing', 'success'], true))
                            <div class="w-100"><a href="{{ route('mobile-payments.show', $refundTx) }}" class="badge bg-{{ $refundTx->statusColor() }} text-decoration-none"><i class="bi bi-phone"></i> {{ __('app.mm_refund') }}: {{ __('app.mm_status_' . $refundTx->status) }}</a></div>
                        @elseif(auth()->user()->hasRole(['admin', 'owner']))
                            <form action="{{ route('mobile-payments.refund', $n) }}" method="POST" class="w-100" data-confirm="{{ __('app.mm_refund_confirm') }}">
                                @csrf
                                <div class="input-group input-group-sm">
                                    <input type="tel" name="phone" class="form-control" value="{{ $order->customer?->phone }}" required>
                                    <button class="btn btn-outline-success"><i class="bi bi-phone"></i> {{ __('app.mm_refund_send', ['provider' => __('app.' . $n->refund_method)]) }}</button>
                                </div>
                            </form>
                        @endif
                    @endif
                    @can("void", $n)
                    <form action="{{ route("credit-notes.void", $n) }}" method="POST" class="collapse w-100" id="voidCn{{ $n->id }}" data-confirm="{{ __('app.cn_void_confirm') }}">
                        @csrf
                        <div class="input-group input-group-sm">
                            <input type="text" name="void_reason" class="form-control" maxlength="255" required placeholder="{{ __('app.cn_void_reason') }}" aria-label="{{ __('app.cn_void_reason') }}">
                            <button class="btn btn-danger">{{ __('app.cn_void') }}</button>
                        </div>
                    </form>
                    @endcan
                </div>
            @endforeach
        @endif
    </div>
</div>

@if(session("print_credit_note"))
<span hidden data-open-on-load="{{ route("credit-notes.show", [session("print_credit_note"), "print" => 1]) }}"></span>
@endif
