@extends("layouts.admin")
@section("title", __('app.order_detail'))
@section("page_title", __('app.order_detail') . " #" . $order->order_number)

@section("content")
<div class="row">
    <div class="col-md-8">
        {{-- Order information --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-1"></i> {{ __('app.order_info') }}</span>
                <span class="badge bg-{{ $order->status_color }} fs-6">{{ $order->status_label }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.order_number') }}</small>
                        <span class="fw-semibold">{{ $order->order_number }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.customer') }}</small>
                        <span class="fw-semibold">{{ $order->customer->name }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.phone') }}</small>
                        <span>{{ $order->customer->phone }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.entry_date') }}</small>
                        <span>{{ $order->received_at->format("d/m/Y") }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.delivery_date') }}</small>
                        <span>{{ $order->delivery_date?->format("d/m/Y") ?? "-" }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.completion_date') }}</small>
                        <span>{{ ($order->completed_at ?? $order->delivery_date)?->format("d/m/Y") ?? "-" }}</span>
                    </div>
                    <div class="col-md-4 mb-2">
                        <small class="text-muted d-block">{{ __('app.total') }}</small>
                        <span class="fw-bold fs-5 text-primary">{{ number_format($order->total_price, 0, ".", ",") }} Mts</span>
                    </div>
                </div>
                @if($order->notes)
                    <div class="mt-2 p-3 bg-light rounded">
                        <small class="text-muted d-block">{{ __('app.notes') }}:</small>
                        {{ $order->notes }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Service items --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-box-seam me-1"></i> {{ __('app.service_items') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.service') }}</th>
                            <th>{{ __('app.type') }}</th>
                            <th>{{ __('app.weight') }} / {{ __('app.quantity') }}</th>
                            <th>{{ __('app.unit_price') }}</th>
                            <th>{{ __('app.subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->item_name }}</td>
                            <td><span class="badge bg-{{ ["by_weight" => "info", "by_piece" => "warning", "by_package" => "success"][$item->type] ?? "secondary" }}">{{ $item->type === 'by_weight' ? __('app.by_weight') : ($item->type === 'by_package' ? __('app.by_package') : __('app.by_piece')) }}</span></td>
                            <td>
                                @if($item->type == "by_weight")
                                    {{ $item->weight }} kg
                                @elseif($item->type == "by_package")
                                    {{ $item->amount }} × {{ round($item->weight / max(1, $item->amount), 2) }} kg
                                @else
                                    {{ $item->amount }} {{ __('app.pieces') }}
                                @endif
                            </td>
                            <td>{{ number_format($item->unit_price, 0, ".", ",") }} Mts</td>
                            <td>{{ number_format($item->subtotal, 0, ".", ",") }} Mts</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @if((float) ($order->iva_amount ?? 0) > 0)
                        <tr>
                            <td colspan="4" class="text-end">{{ __('app.subtotal') }}:</td>
                            <td>{{ number_format($order->subtotal ?? $order->total_price, 0, ".", ",") }} Mts</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end">{{ __('app.iva') }} {{ rtrim(rtrim(number_format((float) $order->iva_rate, 2, ".", ","), "0"), ".") }}%:</td>
                            <td>{{ number_format($order->iva_amount, 0, ".", ",") }} Mts</td>
                        </tr>
                        @endif
                        <tr class="table-active">
                            <th colspan="4" class="text-end">{{ __('app.total') }}:</th>
                            <th class="fw-bold">{{ number_format($order->total_price, 0, ".", ",") }} Mts</th>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        {{-- Status Update --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-arrow-repeat me-1"></i> {{ __('app.update_status') }}</div>
            <div class="card-body">
                @if($order->status === "completed")
                    <div class="text-center py-3">
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mx-auto mb-3"
                            style="width: 64px; height: 64px; font-size: 1.75rem;">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <h5 class="text-success fw-bold mb-1">{{ __('app.transaction_completed') }}</h5>
                        <p class="text-muted small mb-0">{{ __('app.status_locked_notice') }}</p>
                    </div>
                @else
                    <form action="{{ route("orders.update", $order) }}" method="POST">
                        @csrf @method("PUT")
                        <input type="hidden" name="customer_id" value="{{ $order->customer_id }}">
                        <input type="hidden" name="received_at" value="{{ $order->received_at->format("Y-m-d") }}">
                        <div class="mb-3">
                            <label class="form-label">{{ __('app.status') }}</label>
                            <select name="status" class="form-select">
                                @foreach($order->allowedStatuses() as $st)
                                    <option value="{{ $st }}" {{ $order->status == $st ? "selected" : "" }}>
                                        {{ __('app.status_' . $st) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('app.completion_date') }}</label>
                            <input type="date" name="completed_at" class="form-control" value="{{ old("completed_at", ($order->completed_at ?? $order->delivery_date)?->format("Y-m-d") ?? "") }}">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> {{ __('app.update_status') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Payments --}}
        @php
            $totalPaid = $order->payments->sum("amount");
            $remaining = $order->total_price - $totalPaid;
        @endphp
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-wallet me-1"></i> {{ __('app.payment') }}</span>
                @if($order->status === "completed")
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
                    <span class="fw-bold">{{ number_format($order->total_price, 0, ".", ",") }} Mts</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>{{ __('app.paid_amount') }}</span>
                    <span class="text-success fw-bold">{{ number_format($totalPaid, 0, ".", ",") }} Mts</span>
                </div>
                @if($remaining > 0)
                <div class="d-flex justify-content-between">
                    <span>{{ __('app.remaining_amount') }}</span>
                    <span class="text-danger fw-bold">{{ number_format($remaining, 0, ".", ",") }} Mts</span>
                </div>
                @else
                <div class="d-flex justify-content-between">
                    <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('app.paid') }}</span>
                </div>
                @endif

                @if($order->payments->count() > 0)
                    <hr>
                    <h6>{{ __('app.payment_history') }}</h6>
                    @foreach($order->payments as $pm)
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ $pm->paid_at->format("d/m/Y") }} <span class="badge bg-light text-dark">{{ __('app.' . $pm->method) }}</span></span>
                            <span class="fw-semibold d-flex align-items-center gap-1">
                                {{ number_format($pm->amount, 0, ".", ",") }} Mts
                                <a href="{{ route("payments.receipt", $pm) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1" title="{{ __('app.print_receipt') }}">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </span>
                            @if($order->status !== "completed" && !auth()->user()->isCashier())
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#editPm{{ $pm->id }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ route("payments.destroy", $pm) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('app.delete_payment_confirm') }}')">
                                    @csrf @method("DELETE")
                                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x"></i></button>
                                </form>
                            </div>
                            @endif
                        </div>
                        {{-- Edit form inline --}}
                        @if($order->status !== "completed" && !auth()->user()->isCashier())
                        <div class="collapse mt-2 mb-2 p-2 bg-light rounded" id="editPm{{ $pm->id }}">
                            <form action="{{ route("payments.update", $pm) }}" method="POST">
                                @csrf @method("PATCH")
                                <div class="row g-2">
                                    <div class="col-4">
                                        <input type="date" name="paid_at" class="form-control form-control-sm" value="{{ $pm->paid_at->format("Y-m-d") }}" required>
                                    </div>
                                    <div class="col-3">
                                        <input type="number" name="amount" class="form-control form-control-sm" value="{{ $pm->amount }}" step="1" min="1" max="{{ round($order->total_price - ($totalPaid - $pm->amount), 2) }}" required>
                                    </div>
                                    <div class="col-3">
                                        <select name="method" class="form-select form-select-sm">
                                            @foreach(\App\Models\Payment::METHODS as $m)
                                                <option value="{{ $m }}" {{ $pm->method == $m ? "selected" : "" }}>{{ __('app.' . $m) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-2">
                                        <button class="btn btn-sm btn-primary w-100">{{ __('app.save') }}</button>
                                    </div>
                                </div>
                                <input type="text" name="note" class="form-control form-control-sm mt-1" placeholder="{{ __('app.notes') }}" value="{{ $pm->note }}">
                            </form>
                        </div>
                        @endif
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route("orders.index") }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.back') }}</a>
    <a href="{{ route("orders.invoice", $order) }}" class="btn btn-outline-primary" target="_blank"><i class="bi bi-printer"></i> {{ __('app.print_invoice') }}</a>
    <a href="{{ route("orders.label", $order) }}" class="btn btn-outline-info" target="_blank"><i class="bi bi-tag"></i> {{ __('app.print_label') }}</a>
    @if(!auth()->user()->isCashier())
    <form action="{{ route("orders.destroy", $order) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('app.delete_order_confirm') }}')">
        @csrf @method("DELETE")
        <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('app.delete') }}</button>
    </form>
    @endif
</div>
@endsection
