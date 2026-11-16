@extends("layouts.admin")
@section("title", __('app.payment'))
@section("page_title", __('app.payment') . " #" . $order->order_number)

@section("content")
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-receipt me-1"></i> {{ __('app.order_info') }}</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>{{ __('app.customer') }}:</span>
                    <span class="fw-semibold">{{ $order->customer->name }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>{{ __('app.total') }}:</span>
                    <span class="fw-bold fs-5">{{ number_format($order->total_price, 0, ".", ",") }} Mts</span>
                </div>
                @php $paid = $order->payments->sum("amount"); $remaining = $order->total_price - $paid; @endphp
                <div class="d-flex justify-content-between mb-2">
                    <span>{{ __('app.already_paid') }}:</span>
                    <span class="text-success">{{ number_format($paid, 0, ".", ",") }} Mts</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>{{ __('app.remaining') }}:</span>
                    <span class="text-{{ $remaining > 0 ? "danger" : "success" }} fw-bold">{{ number_format($remaining, 0, ".", ",") }} Mts</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-cash me-1"></i> {{ __('app.payment_form') }}</div>
            <div class="card-body">
                <form action="{{ route("payments.store") }}" method="POST">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->id }}">
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.payment_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="paid_at" class="form-control @error("paid_at") is-invalid @enderror" value="{{ old("paid_at", date("Y-m-d")) }}" required>
                        @error("paid_at") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.payment_amount') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="amount" class="form-control @error("amount") is-invalid @enderror" value="{{ old("amount", $remaining > 0 ? $remaining : 0) }}" required min="1" max="{{ round($remaining, 2) }}">
                            <span class="input-group-text">Mts</span>
                        </div>
                        @error("amount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.payment_method') }} <span class="text-danger">*</span></label>
                        <select name="method" class="form-select" required>
                            @foreach(\App\Models\Payment::METHODS as $m)
                                <option value="{{ $m }}" {{ old("method") == $m ? "selected" : "" }}>{{ __('app.' . $m) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.notes') }}</label>
                        <input type="text" name="note" class="form-control" value="{{ old("note") }}">
                    </div>
                    <button type="submit" class="btn btn-success w-100" @if($remaining <= 0) disabled title="{{ __('app.order_fully_paid') }}" @endif>
                        <i class="bi bi-check-lg"></i> {{ __('app.confirm_payment') }}
                    </button>
                </form>
            </div>
        </div>

        @if($order->payments->count() > 0)
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i> {{ __('app.payment_history') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.date') }}</th>
                            <th>{{ __('app.method') }}</th>
                            <th>{{ __('app.total') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->payments as $pm)
                        <tr>
                            <td>{{ $pm->paid_at->format("d/m/Y") }}</td>
                            <td><span class="badge bg-light text-dark">{{ __('app.' . $pm->method) }}</span></td>
                            <td>{{ number_format($pm->amount, 0, ".", ",") }} Mts</td>
                            <td class="text-end">
                                <a href="{{ route("payments.receipt", $pm) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('app.print_receipt') }}">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
