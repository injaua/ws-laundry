@extends("layouts.document")

{{-- Purchase order sent to the supplier. --}}
@php($supplier = $purchase->supplier)

@section("title", __('app.purchase_order') . " " . $purchase->po_number)
@section("doc_type", __('app.purchase_order'))
@section("doc_meta")
    <div><strong>{{ $purchase->po_number }}</strong></div>
    <div>{{ __('app.order_date') }}: {{ $purchase->order_date?->format(date_fmt()) ?? "—" }}</div>
    @if($purchase->expected_date)<div>{{ __('app.expected_date') }}: {{ $purchase->expected_date->format(date_fmt()) }}</div>@endif
    <div class="muted">{{ $purchase->status_label }}</div>
@endsection
@section("no_bank_details", "1")

@section("content")
<div class="parties">
    <div>
        <div class="label">{{ __('app.supplier') }}</div>
        <strong>{{ $supplier?->name }}</strong>
        @if($supplier?->nit)<div>{{ __('app.nit') }}: {{ $supplier->nit }}</div>@endif
        @if($supplier?->address)<div class="muted">{{ $supplier->address }}{{ $supplier->city ? ", " . $supplier->city : "" }}</div>@endif
        <div class="muted">{{ collect([$supplier?->phone, $supplier?->email])->filter()->implode(" · ") }}</div>
    </div>
    <div>
        @if($supplier?->contact_person)<div>{{ __('app.contact_person') }}: {{ $supplier->contact_person }}</div>@endif
        <div class="muted">{{ __('app.created_by') }}: {{ $purchase->user?->name }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>{{ __('app.item') }}</th>
            <th class="r">{{ __('app.quantity') }}</th>
            <th class="r">{{ __('app.unit_cost') }}</th>
            <th class="r">{{ __('app.subtotal') }} ({{ currency_symbol() }})</th>
        </tr>
    </thead>
    <tbody>
        @foreach($purchase->items as $line)
            <tr>
                <td>{{ $line->item?->name }}</td>
                <td class="r">{{ qty($line->quantity) }} {{ $line->item ? __('app.unit_' . $line->item->unit) : "" }}</td>
                <td class="r">{{ num($line->unit_cost) }}</td>
                <td class="r">{{ num($line->subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr class="grand"><td>{{ __('app.total') }}</td><td class="r">{{ money($purchase->total) }}</td></tr>
</table>

@if($purchase->notes)
    <div class="note"><div class="label">{{ __('app.notes') }}</div>{{ $purchase->notes }}</div>
@endif

<table class="signatures">
    <tr><td></td><td><div class="line">{{ __('app.authorized_by') }}</div></td></tr>
</table>
@endsection
