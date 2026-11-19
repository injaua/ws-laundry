@extends("layouts.document")

@section("title", __('app.statement_of_account') . " — " . $customer->name)
@section("doc_type", __('app.statement_of_account'))
@section("doc_meta")
    <div>{{ $from->format(date_fmt()) }} – {{ $to->format(date_fmt()) }}</div>
@endsection
@section("toolbar")
    <form class="inline-gap" method="GET">
        <input class="p-4px" type="date" name="from" value="{{ $from->toDateString() }}">
        <input class="p-4px" type="date" name="to" value="{{ $to->toDateString() }}">
        <button type="submit">{{ __('app.filter') }}</button>
    </form>
    @if($customer->normalizedWhatsapp())
        <a href="https://wa.me/{{ $customer->normalizedWhatsapp() }}?text={{ rawurlencode(__('app.statement_whatsapp_text', ["customer" => $customer->name, "balance" => money($data["closing"]), "company" => \App\Support\SystemSettings::companyName(), "date" => $to->format(date_fmt())])) }}" target="_blank">WhatsApp</a>
    @endif
@endsection

@section("content")
<div class="parties">
    <div>
        <div class="label">{{ __('app.customer') }}</div>
        <strong>{{ $customer->fiscalName() }}</strong>
        @if($customer->nuit)<div>{{ __('app.nit') }}: {{ $customer->nuit }}</div>@endif
        @if($customer->fiscalAddress())<div class="muted">{{ $customer->fiscalAddress() }}</div>@endif
    </div>
    <div>
        <div class="label">{{ __('app.summary') }}</div>
        <div>{{ __('app.opening_balance') }}: <strong>{{ money($data["opening"]) }}</strong></div>
        <div>{{ __('app.closing_balance') }}: <strong>{{ money($data["closing"]) }}</strong></div>
        <div class="muted">{{ __('app.credit_limit') }}: {{ $customer->credit_limit > 0 ? money($customer->credit_limit) : __('app.unlimited') }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>{{ __('app.date') }}</th>
            <th>{{ __('app.description') }}</th>
            <th>{{ __('app.reference') }}</th>
            <th>{{ __('app.invoice') }}</th>
            <th class="r">{{ __('app.debit') }}</th>
            <th class="r">{{ __('app.credit') }}</th>
            <th class="r">{{ __('app.balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $from->format(date_fmt()) }}</td>
            <td colspan="5"><em>{{ __('app.opening_balance') }}</em></td>
            <td class="r"><strong>{{ num($data["opening"]) }}</strong></td>
        </tr>
        @forelse($data["lines"] as $line)
        <tr>
            <td>{{ $line["date"]->format(date_fmt()) }}</td>
            <td>{{ __('app.statement_' . $line["type"]) }}@if($line["method"]) <span class="muted">({{ __('app.' . $line["method"]) }})</span>@endif</td>
            <td>{{ $line["reference"] }}</td>
            <td>{{ $line["invoice"] ?? "-" }}</td>
            <td class="r">{{ $line["debit"] > 0 ? num($line["debit"]) : "" }}</td>
            <td class="r">{{ $line["credit"] > 0 ? num($line["credit"]) : "" }}</td>
            <td class="r">{{ num($line["balance"]) }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="muted text-center">{{ __('app.no_movements_in_period') }}</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <th colspan="4">{{ __('app.closing_balance') }}</th>
            <th class="r">{{ num($data["debits"]) }}</th>
            <th class="r">{{ num($data["credits"]) }}</th>
            <th class="r">{{ num($data["closing"]) }}</th>
        </tr>
    </tfoot>
</table>

@if($openInvoices->isNotEmpty())
<div class="label mt-16px">{{ __('app.open_invoices') }}</div>
<table>
    <thead>
        <tr>
            <th>{{ __('app.invoice_number') }}</th>
            <th>{{ __('app.issue_date') }}</th>
            <th>{{ __('app.due_date') }}</th>
            <th class="r">{{ __('app.total') }}</th>
            <th class="r">{{ __('app.outstanding_balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($openInvoices as $invoice)
        <tr>
            <td>{{ $invoice->number }}</td>
            <td>{{ $invoice->issue_date?->format(date_fmt()) }}</td>
            <td style="{{ $invoice->isOverdue() ? "color:#b91c1c;font-weight:bold" : "" }}">{{ $invoice->due_date?->format(date_fmt()) }}</td>
            <td class="r">{{ num($invoice->total) }}</td>
            <td class="r">{{ num($invoice->balance()) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
@endsection
