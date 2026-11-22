@extends("layouts.admin")
@section("title", __('app.new_invoice'))
@section("page_title", __('app.new_invoice'))
@section("page_subtitle", __('app.new_invoice_subtitle'))

@section("content")
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route("invoices.create") }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small">{{ __('app.customer') }}</label>
                <select name="customer_id" class="form-select" required>
                    <option value="">{{ __('app.select_customer') }}</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected($customer?->id === $c->id)>{{ $c->name }}@if($c->billsMonthly()) · {{ __('app.billing_cycle_monthly') }}@endif</option>
                    @endforeach
                </select>
                @if($customers->isEmpty())
                    <div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i> {{ __('app.invoice_no_credit_customers') }}</div>
                @else
                    <div class="form-text">{{ __('app.invoice_credit_customers_only') }}</div>
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label small">{{ __('app.period_start') }}</label>
                <input type="date" name="period_start" class="form-control" value="{{ $start->toDateString() }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small">{{ __('app.period_end') }}</label>
                <input type="date" name="period_end" class="form-control" value="{{ $end->toDateString() }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary w-100" title="{{ __('app.search') }}"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

@if($customer)
<form method="POST" action="{{ route("invoices.store") }}">
    @csrf
    <input type="hidden" name="customer_id" value="{{ $customer->id }}">
    <input type="hidden" name="period_start" value="{{ $start->toDateString() }}">
    <input type="hidden" name="period_end" value="{{ $end->toDateString() }}">

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check me-1"></i> {{ __('app.invoice_select_orders') }}</span>
            <span class="small text-muted">{{ __('app.invoice_eligible_hint') }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="w-40px"><input type="checkbox" class="form-check-input" id="checkAll" data-check-all=".order-check" checked></th>
                            <th>{{ __('app.order_number') }}</th>
                            <th>{{ __('app.completion_date') }}</th>
                            <th>{{ __('app.purchase_order_ref') }}</th>
                            <th class="text-end">{{ __('app.total') }}</th>
                            <th class="text-end">{{ __('app.outstanding_balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr>
                            <td><input type="checkbox" class="form-check-input order-check" name="orders[]" value="{{ $order->id }}" checked></td>
                            <td class="fw-semibold"><a href="{{ route("orders.show", $order) }}" target="_blank">{{ $order->order_number }}</a></td>
                            <td class="small">{{ $order->completed_at?->format(date_fmt()) }}</td>
                            <td class="small">{{ $order->purchase_order_ref ?? "-" }}</td>
                            <td class="text-end">{{ num($order->netTotal()) }}</td>
                            <td class="text-end fw-semibold">{{ num($order->remainingBalance()) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ __('app.invoice_no_orders') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->isNotEmpty())
        <div class="card-footer">
            @error("orders") <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <label class="form-label small" for="notes">{{ __('app.notes') }}</label>
            <textarea name="notes" id="notes" rows="2" maxlength="1000" class="form-control mb-2">{{ old("notes") }}</textarea>
            <button type="submit" class="btn btn-primary"><i class="bi bi-file-earmark-plus"></i> {{ __('app.create_draft_invoice') }}</button>
        </div>
        @endif
    </div>
</form>
@endif
@endsection

