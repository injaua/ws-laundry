@extends("layouts.admin")
@section("title", $session->label())
@section("page_title", __('app.till') . " " . $session->label())

@php($diffClass = fn ($v) => (float) $v < 0 ? "text-danger" : ((float) $v > 0 ? "text-warning" : "text-success"))

@section("content")
<div class="page-title d-print-none">
    <a href="{{ route("cash-sessions.index") }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> {{ __('app.till_history') }}</a>
    <div class="d-flex gap-2">
        @can("close", $session)
            <a href="{{ route("cash-sessions.close-form", $session) }}" class="btn btn-danger"><i class="bi bi-lock"></i> {{ __('app.close_till') }}</a>
        @endcan
        @unless($session->isOpen())
            <button type="button" class="btn btn-outline-primary" data-print><i class="bi bi-printer"></i> {{ __('app.print') }}</button>
            <a href="{{ route("cash-sessions.document", $session) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> {{ __('app.cash_session_report') }}</a>
        @endunless
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-safe me-1"></i> {{ $session->label() }}</span>
                <span class="badge bg-{{ $session->isOpen() ? "success" : "secondary" }}">{{ __('app.till_status_' . $session->status) }}</span>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">{{ __('app.operator') }}</dt><dd class="col-7">{{ $session->user->name }}</dd>
                    <dt class="col-5">{{ __('app.opened_at') }}</dt><dd class="col-7">{{ $session->opened_at->format(datetime_fmt()) }}</dd>
                    <dt class="col-5">{{ __('app.opening_float') }}</dt><dd class="col-7">{{ money($session->opening_float) }}</dd>
                    <dt class="col-5">{{ __('app.payments_count') }}</dt><dd class="col-7">{{ $session->payments->count() }}</dd>
                    @unless($session->isOpen())
                        <dt class="col-5">{{ __('app.closed_at') }}</dt><dd class="col-7">{{ $session->closed_at->format(datetime_fmt()) }}</dd>
                        <dt class="col-5">{{ __('app.closed_by') }}</dt><dd class="col-7">{{ $session->closer?->name ?? "-" }}</dd>
                        @if($session->closing_note)
                            <dt class="col-5">{{ __('app.closing_note') }}</dt><dd class="col-7">{{ $session->closing_note }}</dd>
                        @endif
                    @endunless
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @if($session->isOpen())
            @if($expected)
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-graph-up me-1"></i> {{ __('app.expected_now') }}</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <tbody>
                            @foreach($expected as $method => $amount)
                                <tr><td>{{ __('app.' . $method) }}</td><td class="text-end fw-semibold">{{ money($amount) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @else
            <div class="alert alert-info small"><i class="bi bi-eye-slash me-1"></i> {{ __('app.blind_count_open_help') }}</div>
            @endif

            @can("move", $session)
            <div class="card mb-3 d-print-none">
                <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i> {{ __('app.cash_movement') }}</div>
                <div class="card-body">
                    <form action="{{ route("cash-sessions.movements.store", $session) }}" method="POST" class="row g-2">
                        @csrf
                        <div class="col-md-3">
                            <select name="type" class="form-select" required>
                                <option value="withdrawal" @selected(old("type") === "withdrawal")>{{ __('app.cash_withdrawal') }}</option>
                                <option value="deposit" @selected(old("type") === "deposit")>{{ __('app.cash_deposit') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="amount" class="form-control @error("amount") is-invalid @enderror" placeholder="{{ __('app.amount') }}" value="{{ old("amount") }}" min="1" step="0.01" required>
                            @error("amount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="reason" class="form-control @error("reason") is-invalid @enderror" placeholder="{{ __('app.reason') }}" value="{{ old("reason") }}" maxlength="255" required>
                            @error("reason") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                        </div>
                    </form>
                    <div class="form-text">{{ __('app.cash_movement_help') }}</div>
                </div>
            </div>
            @endcan
        @else
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-calculator me-1"></i> {{ __('app.till_count') }}</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('app.method') }}</th>
                                <th class="text-end">{{ __('app.expected') }}</th>
                                <th class="text-end">{{ __('app.counted') }}</th>
                                <th class="text-end">{{ __('app.difference') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($session->orderedCounts() as $count)
                            <tr>
                                <td>{{ __('app.' . $count->method) }}</td>
                                <td class="text-end">{{ money($count->expected) }}</td>
                                <td class="text-end">{{ money($count->counted) }}</td>
                                <td class="text-end fw-semibold {{ $diffClass($count->difference) }}">{{ (float) $count->difference > 0 ? "+" : "" }}{{ money($count->difference) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td>{{ __('app.total') }}</td>
                                <td class="text-end">{{ money($session->counts->sum("expected")) }}</td>
                                <td class="text-end">{{ money($session->counts->sum("counted")) }}</td>
                                <td class="text-end {{ $diffClass($session->totalDifference()) }}">{{ $session->totalDifference() > 0 ? "+" : "" }}{{ money($session->totalDifference()) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i> {{ __('app.cash_movements') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th>{{ __('app.date') }}</th><th>{{ __('app.type') }}</th><th>{{ __('app.reason') }}</th><th class="text-end">{{ __('app.amount') }}</th></tr>
                    </thead>
                    <tbody>
                        @forelse($session->movements as $movement)
                        <tr>
                            <td class="small">{{ $movement->created_at->format("d/m H:i") }}</td>
                            <td><span class="badge bg-{{ $movement->type === "withdrawal" ? "warning text-dark" : "info text-dark" }}">{{ __('app.cash_' . $movement->type) }}</span></td>
                            <td class="small">{{ $movement->reason }}</td>
                            <td class="text-end">{{ $movement->type === "withdrawal" ? "−" : "+" }}{{ money($movement->amount) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted small py-3">{{ __('app.no_cash_movements') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        @if($canSeeExpected)
        <div class="card">
            <div class="card-header"><i class="bi bi-receipt me-1"></i> {{ __('app.till_payments') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>{{ __('app.order_number') }}</th><th>{{ __('app.method') }}</th><th class="text-end">{{ __('app.amount') }}</th></tr>
                    </thead>
                    <tbody>
                        @forelse($session->payments as $payment)
                        <tr>
                            <td>
                                @if($payment->order)
                                    <a href="{{ route("orders.show", $payment->order) }}">{{ $payment->order->order_number }}</a>
                                @else - @endif
                            </td>
                            <td>{{ __('app.' . $payment->method) }}</td>
                            <td class="text-end">{{ money($payment->amount) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted small py-3">{{ __('app.no_payments_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if($paymentTotals->isNotEmpty())
                    <tfoot>
                        @foreach($paymentTotals as $method => $t)
                        <tr class="small text-muted"><td>{{ __('app.' . $method) }}</td><td>{{ $t["count"] }}×</td><td class="text-end">{{ money($t["total"]) }}</td></tr>
                        @endforeach
                    </tfoot>
                    @endif
                </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
