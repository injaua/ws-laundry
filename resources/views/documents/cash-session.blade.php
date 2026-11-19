@extends("layouts.document")

{{-- Till report: shift details, payments by method, cash movements and the closing count. --}}
@section("title", __('app.cash_session_report') . " " . $session->label())
@section("doc_type", __('app.cash_session_report'))
@section("doc_meta")
    <div><strong>{{ $session->label() }}</strong></div>
    <div>{{ __('app.opened_at') }}: {{ $session->opened_at->format(datetime_fmt()) }}</div>
    @if($session->closed_at)<div>{{ __('app.closed_at') }}: {{ $session->closed_at->format(datetime_fmt()) }}</div>@endif
@endsection
@section("no_bank_details", "1")

@section("content")
<div class="parties">
    <div>
        <div class="label">{{ __('app.operator') }}</div>
        <strong>{{ $session->user?->name }}</strong>
        <div>{{ __('app.opening_float') }}: {{ money($session->opening_float) }}</div>
    </div>
    <div>
        @if($session->closed_at)
            <div class="label">{{ __('app.closed_by') }}</div>
            <strong>{{ $session->closer?->name }}</strong>
            @if($session->closing_note)<div class="muted">{{ $session->closing_note }}</div>@endif
        @else
            <div class="label">{{ __('app.till') }}</div>
            <strong>{{ __('app.cash_session_open') }}</strong>
        @endif
    </div>
</div>

<div class="label">{{ __('app.till_payments') }}</div>
<table>
    <thead>
        <tr><th>{{ __('app.method') }}</th><th class="r">{{ __('app.payments_count') }}</th><th class="r">{{ __('app.total') }} ({{ currency_symbol() }})</th></tr>
    </thead>
    <tbody>
        @forelse($paymentTotals as $method => $row)
            <tr><td>{{ __('app.' . $method) }}</td><td class="r">{{ $row["count"] }}</td><td class="r">{{ num($row["total"]) }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">{{ __('app.no_payments_yet') }}</td></tr>
        @endforelse
    </tbody>
</table>

@if($session->movements->isNotEmpty())
    <div class="label">{{ __('app.cash_movements') }}</div>
    <table>
        <thead>
            <tr><th>{{ __('app.date') }}</th><th>{{ __('app.type') }}</th><th>{{ __('app.reason') }}</th><th class="r">{{ __('app.amount') }}</th></tr>
        </thead>
        <tbody>
            @foreach($session->movements as $movement)
                <tr>
                    <td>{{ $movement->created_at->format(datetime_fmt()) }}</td>
                    <td>{{ __('app.cash_' . $movement->type) }}</td>
                    <td>{{ $movement->reason }}</td>
                    <td class="r">{{ num($movement->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($canSeeExpected && $session->counts->isNotEmpty())
    <div class="label">{{ __('app.till_count') }}</div>
    <table>
        <thead>
            <tr><th>{{ __('app.method') }}</th><th class="r">{{ __('app.expected') }}</th><th class="r">{{ __('app.counted') }}</th><th class="r">{{ __('app.difference') }}</th></tr>
        </thead>
        <tbody>
            @foreach($session->orderedCounts() as $count)
                <tr>
                    <td>{{ __('app.' . $count->method) }}</td>
                    <td class="r">{{ num($count->expected) }}</td>
                    <td class="r">{{ num($count->counted) }}</td>
                    <td class="r {{ (float) $count->difference < 0 ? "text-loss" : ((float) $count->difference > 0 ? "text-gain" : "") }}">{{ num($count->difference) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="signatures">
    <tr>
        <td><div class="line">{{ __('app.operator') }}</div></td>
        <td><div class="line">{{ __('app.closed_by') }}</div></td>
    </tr>
</table>
@endsection
