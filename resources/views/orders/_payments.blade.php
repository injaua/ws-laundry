{{-- Payments of the order: balance, history, new payment, inline edit. --}}
{{-- Payments --}}
@php(["paid" => $totalPaid, "credited" => $credited, "refunded" => $refunded, "remaining" => $remaining] = $order->paymentSummary())
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-wallet me-1"></i> {{ __('app.payment') }}
            @if($order->is_credit)
                <span class="badge bg-dark ms-1"><i class="bi bi-credit-card"></i> {{ __('app.billed_on_credit') }}</span>
            @endif
        </span>
        @if(auth()->user()->cannot("update", $order))
            {{-- read-only (accountant) --}}
        @elseif($order->is_credit && $remaining > 0)
            <a href="{{ route("orders.payment", $order) }}" class="btn btn-sm btn-success">
                <i class="bi bi-plus"></i> {{ __('app.pay') }}
            </a>
        @elseif($order->status === "completed")
            <span class="badge bg-success fs-6"><i class="bi bi-check-circle"></i> {{ __('app.paid_and_completed') }}</span>
        @elseif($remaining <= 0)
            <button type="button" class="btn btn-sm btn-success" disabled title="{{ __('app.order_fully_paid') }}">
                <i class="bi bi-check-circle"></i> {{ __('app.pay') }}
            </button>
        @else
            <a href="{{ route("orders.payment", $order) }}" class="btn btn-sm btn-success">
                <i class="bi bi-plus"></i> {{ __('app.pay') }}
            </a>
        @endif
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
            <span>{{ __('app.total') }}:</span>
            <span class="fw-bold">{{ money($order->total_price) }}</span>
        </div>
        @if($credited > 0)
        <div class="d-flex justify-content-between mb-2">
            <span>{{ __('app.credit_notes') }}</span>
            <span class="text-danger">− {{ money($credited) }}</span>
        </div>
        @endif
        <div class="d-flex justify-content-between mb-2">
            <span>{{ __('app.paid_amount') }}</span>
            <span class="text-success fw-bold">{{ money($totalPaid) }}</span>
        </div>
        @if($refunded > 0)
        <div class="d-flex justify-content-between mb-2">
            <span>{{ __('app.refunds') }}</span>
            <span class="text-danger">− {{ money($refunded) }}</span>
        </div>
        @endif
        @if($remaining > 0)
        <div class="d-flex justify-content-between">
            <span>{{ __('app.remaining_amount') }}</span>
            <span class="text-danger fw-bold">{{ money($remaining) }}</span>
        </div>
        @else
        <div class="d-flex justify-content-between">
            <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('app.paid') }}</span>
        </div>
        @endif

        @if($order->is_credit)
        <hr>
        <div class="d-flex justify-content-between mb-1">
            <span class="small text-muted">{{ __('app.due_date') }}</span>
            <span class="{{ $remaining > 0 && $order->isOverdue() ? "text-danger fw-bold" : "fw-semibold" }}">{{ $order->due_date?->format(date_fmt()) ?? ($order->customer?->billsMonthly() && !$order->invoice_id ? __('app.awaiting_monthly_invoice') : "-") }}</span>
        </div>
        @if($order->invoice)
        <div class="d-flex justify-content-between mb-1">
            <span class="small text-muted">{{ __('app.invoice') }}</span>
            @if(auth()->user()->isCashier())
                <span class="fw-semibold">{{ $order->invoice->label() }}</span>
            @else
                <a href="{{ route("invoices.show", $order->invoice) }}" class="fw-semibold">{{ $order->invoice->label() }}</a>
            @endif
        </div>
        @endif
        @endif
        @if($order->purchase_order_ref)
        <div class="d-flex justify-content-between mb-1">
            <span class="small text-muted">{{ __('app.purchase_order_ref') }}</span>
            <span class="fw-semibold">{{ $order->purchase_order_ref }}</span>
        </div>
        @endif
        @if($order->is_credit)
        @if($remaining > 0 && $order->isOverdue())
        <div class="text-end"><span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> {{ __('app.days_overdue', ["days" => $order->daysOverdue()]) }}</span></div>
        @endif
        @if($order->credit_override_note && !auth()->user()->isCashier())
        <div class="small text-warning mt-1"><i class="bi bi-shield-exclamation me-1"></i>{{ __('app.credit_override_note') }}: {{ $order->credit_override_note }}</div>
        @endif
        @endif

        @php($bouncedPayments = $order->bouncedPayments)
        @if($bouncedPayments->isNotEmpty())
            <div class="alert alert-danger small py-2 mt-2 mb-0">
                <i class="bi bi-exclamation-octagon"></i> {{ __('app.cheque_bounced_on_order') }}
                @foreach($bouncedPayments as $bp)
                    <div>{{ $bp->cheque?->label() }} · {{ money($bp->amount) }} · {{ $bp->cheque?->bounced_at?->format(date_fmt()) }} — {{ $bp->cheque?->bounce_reason }}</div>
                @endforeach
            </div>
        @endif

        @if($order->payments->count() > 0)
            <hr>
            <h6>{{ __('app.payment_history') }}</h6>
            @foreach($order->payments as $pm)
                <div class="d-flex justify-content-between small mb-1">
                    <span>{{ $pm->paid_at->format(date_fmt()) }} <span class="badge bg-light text-dark">{{ __('app.' . $pm->method) }}</span>@if($pm->cheque) <span class="text-muted">{{ $pm->cheque->label() }}</span> <span class="badge bg-{{ $pm->cheque->statusColor() }}">{{ __('app.cheque_status_' . $pm->cheque->status) }}</span>@if($pm->cheque->isPostDated()) <span class="badge bg-warning text-dark" title="{{ __('app.cheque_date') }}">{{ $pm->cheque->cheque_date->format(date_fmt()) }}</span>@endif @elseif($pm->transaction_ref) <span class="font-monospace text-muted" title="{{ __('app.transaction_ref') }}">{{ $pm->transaction_ref }}</span>@endif @if($pm->reconciled_at)<i class="bi bi-check2-all text-success" title="{{ __('app.reconciled') }}"></i>@endif</span>
                    <span class="fw-semibold d-flex align-items-center gap-1">
                        {{ money($pm->amount) }}
                        <a href="{{ route("payments.receipt", $pm) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1" title="{{ __('app.print_receipt') }}">
                            <i class="bi bi-printer"></i>
                        </a>
                        <a href="{{ route("payments.document", $pm) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1" title="{{ __('app.a4_document') }}">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                    </span>
                    @if($order->status !== "completed" && auth()->user()->isManager())
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#editPm{{ $pm->id }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route("payments.destroy", $pm) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_payment_confirm') }}">
                            @csrf @method("DELETE")
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x"></i></button>
                        </form>
                    </div>
                    @endif
                </div>
                {{-- Edit form inline --}}
                @if($order->status !== "completed" && auth()->user()->isManager())
                <div class="collapse mt-2 mb-2 p-2 bg-light rounded" id="editPm{{ $pm->id }}">
                    <form action="{{ route("payments.update", $pm) }}" method="POST">
                        @csrf @method("PATCH")
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="date" name="paid_at" class="form-control form-control-sm" value="{{ $pm->paid_at->format("Y-m-d") }}" required>
                            </div>
                            <div class="col-3">
                                <input type="number" name="amount" class="form-control form-control-sm" value="{{ $pm->amount }}" step="0.01" min="1" max="{{ round($order->total_price - ($totalPaid - $pm->amount), 2) }}" required>
                            </div>
                            <div class="col-3">
                                <select name="method" class="form-select form-select-sm">
                                    @foreach(\App\Models\Payment::acceptedMethods($pm->method) as $m)
                                        <option value="{{ $m }}" {{ $pm->method == $m ? "selected" : "" }}>{{ __('app.' . $m) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-2">
                                <button class="btn btn-sm btn-primary w-100">{{ __('app.save') }}</button>
                            </div>
                        </div>
                        @include("payments._mobile-ref", ["sm" => true, "ref" => $pm->transaction_ref, "phone" => $pm->payer_phone, "tendered" => $pm->tendered, "cheque" => $pm->cheque])
                        <input type="text" name="note" class="form-control form-control-sm mt-1" placeholder="{{ __('app.notes') }}" value="{{ $pm->note }}">
                    </form>
                </div>
                @endif
            @endforeach
        @endif
    </div>
</div>
