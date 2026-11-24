@extends("layouts.admin")
@section("title", __('app.invoices'))
@section("page_title", __('app.invoices'))
@section("page_subtitle", __('app.invoices_subtitle'))

{{-- Unpaid invoices of both kinds (order invoices and consolidated FT) by default; the other
     filters list consolidated invoices only. Consolidated invoices are created from the customer pages. --}}
@php($unpaid = in_array($filter, ["unpaid", "overdue"], true))

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
                    @foreach(\App\Http\Controllers\InvoiceController::LIST_FILTERS as $f)
                        <option value="{{ $f }}" @selected($filter === $f)>{{ __('app.invoice_filter_' . $f) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">{{ __('app.type') }}</label>
                <select name="kind" class="form-select">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="order" @selected($kind === "order")>{{ __('app.invoice_kind_order') }}</option>
                    <option value="invoice" @selected($kind === "invoice")>{{ __('app.invoice_kind_invoice') }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i> {{ __('app.filter') }}</button>
            </div>
        </form>
        @unless($unpaid)
            <div class="form-text">{{ __('app.invoice_filter_consolidated_only') }}</div>
        @endunless
    </div>
</div>

@if($totalOutstanding !== null)
<div class="alert alert-light border d-flex justify-content-between align-items-center">
    <span><i class="bi bi-hourglass-split me-1"></i> {{ __('app.invoices_unpaid_count', ['count' => $rows->total()]) }}</span>
    <span>{{ __('app.outstanding_balance') }}: <strong>{{ money($totalOutstanding) }}</strong></span>
</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.invoice_number') }}</th>
                        <th>{{ __('app.type') }}</th>
                        <th>{{ __('app.customer') }}</th>
                        <th>{{ __('app.date') }}</th>
                        <th>{{ __('app.due_date') }}</th>
                        <th class="text-end">{{ __('app.total') }}</th>
                        <th class="text-end">{{ __('app.outstanding_balance') }}</th>
                        <th class="text-center">{{ __('app.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td class="fw-semibold"><a href="{{ $row["url"] }}">{{ $row["number"] }}</a></td>
                        <td class="small">{{ __('app.invoice_kind_' . $row["kind"]) }}</td>
                        <td>{{ $row["customer"] }}</td>
                        <td class="small">{{ $row["date"]?->format(date_fmt()) ?? "-" }}</td>
                        <td class="small {{ $row["overdue"] ? "text-danger fw-semibold" : "" }}">{{ $row["due_date"]?->format(date_fmt()) ?? "-" }}</td>
                        <td class="text-end">{{ num($row["total"]) }}</td>
                        <td class="text-end fw-semibold">{{ $row["balance"] === null ? "-" : num($row["balance"]) }}</td>
                        <td class="text-center"><span class="badge bg-{{ $row["color"] }}">{{ __('app.invoice_status_' . $row["status"]) }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ $row["print_url"] }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('app.print_invoice') }}"><i class="bi bi-printer"></i></a>
                            @if($row["pay_url"])
                                <a href="{{ $row["pay_url"] }}" class="btn btn-sm btn-outline-success" title="{{ __('app.pay') }}"><i class="bi bi-cash"></i></a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">{{ $unpaid ? __('app.no_unpaid_invoices') : __('app.no_invoices') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="mt-3">{{ $rows->links() }}</div>
@endsection
