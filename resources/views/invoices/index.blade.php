@extends("layouts.admin")
@section("title", __('app.invoices'))
@section("page_title", __('app.invoices'))
@section("page_subtitle", __('app.invoices_subtitle'))

@section("content")
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route("invoices.index") }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">{{ __('app.customer') }}</label>
                <select name="customer_id" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected(request("customer_id") == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">{{ __('app.status') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach(\App\Models\Invoice::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request("status") === $status)>{{ __('app.invoice_status_' . $status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i> {{ __('app.filter') }}</button>
            </div>
            <div class="col-md-3 text-md-end">
                <a href="{{ route("invoices.create") }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('app.new_invoice') }}</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.invoice_number') }}</th>
                        <th>{{ __('app.customer') }}</th>
                        <th>{{ __('app.period') }}</th>
                        <th>{{ __('app.due_date') }}</th>
                        <th class="text-end">{{ __('app.total') }}</th>
                        <th class="text-end">{{ __('app.outstanding_balance') }}</th>
                        <th class="text-center">{{ __('app.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td class="fw-semibold"><a href="{{ route("invoices.show", $invoice) }}">{{ $invoice->label() }}</a></td>
                        <td>{{ $invoice->customer->name }}</td>
                        <td class="small">{{ $invoice->period_start->format(date_fmt()) }} – {{ $invoice->period_end->format(date_fmt()) }}</td>
                        <td class="small {{ $invoice->isOverdue() ? "text-danger fw-semibold" : "" }}">{{ $invoice->due_date?->format(date_fmt()) ?? "-" }}</td>
                        <td class="text-end">{{ num($invoice->total) }}</td>
                        <td class="text-end fw-semibold">{{ $invoice->isVoid() ? "-" : num($invoice->balance()) }}</td>
                        <td class="text-center"><span class="badge bg-{{ $invoice->statusColor() }}">{{ __('app.invoice_status_' . $invoice->displayStatus()) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('app.no_invoices') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="mt-3">{{ $invoices->links() }}</div>
@endsection
