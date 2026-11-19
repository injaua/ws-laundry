@extends("layouts.admin")
@section("title", __('app.courier_cash'))
@section("page_title", __('app.courier_cash'))
@section("page_subtitle", __('app.courier_cash_subtitle'))

@section("content")
@if(!$till)
<div class="alert alert-warning">
    <i class="bi bi-safe"></i> {{ __('app.courier_cash_needs_till') }}
    <a href="{{ route("cash-sessions.current") }}" class="alert-link">{{ __('app.till') }}</a>
</div>
@endif
@error("till") <div class="alert alert-danger">{{ $message }}</div> @enderror

@forelse($pending as $userId => $payments)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-person-badge"></i> <strong>{{ $holders[$userId]?->name ?? "#" . $userId }}</strong></span>
        <span class="fs-5 fw-bold">{{ money($payments->sum("amount")) }}</span>
    </div>
    <ul class="list-group list-group-flush small">
        @foreach($payments as $payment)
        <li class="list-group-item d-flex justify-content-between">
            <span>{{ $payment->paid_at->format(date_fmt()) }} · {{ $payment->order?->order_number }} · {{ $payment->order?->customer?->name }}</span>
            <span>{{ money($payment->amount) }}</span>
        </li>
        @endforeach
    </ul>
    <div class="card-footer">
        <form action="{{ route("logistics.cash.receive", $userId) }}" method="POST" data-confirm="{{ __('app.confirm_receive_cash') }}">
            @csrf
            <button type="submit" class="btn btn-success" @disabled(!$till)><i class="bi bi-box-arrow-in-down"></i> {{ __('app.receive_into_till') }}</button>
            <span class="small text-muted ms-2">{{ __('app.receive_cash_hint') }}</span>
        </form>
    </div>
</div>
@empty
<div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-check2-circle fs-1 d-block mb-2"></i>{{ __('app.no_courier_cash') }}</div></div>
@endforelse
@endsection
