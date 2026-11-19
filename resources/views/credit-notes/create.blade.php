@extends("layouts.admin")
@section("title", __('app.cn_new'))
@section("page_title", __('app.cn_new'))

@section("content")
<div class="row g-3">
    <div class="col-lg-4 order-lg-2">
        <div class="card">
            <div class="card-header"><i class="bi bi-receipt me-1"></i> {{ __('app.invoice') }} {{ $order->order_number }}</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.customer') }}</span><span class="fw-semibold">{{ $order->customer?->name }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.total') }}</span><span>{{ money($order->total_price) }}</span></li>
                @if($order->creditedAmount() > 0)
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.cn_already_credited') }}</span><span class="text-danger">− {{ money($order->creditedAmount()) }}</span></li>
                @endif
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.cn_paid_kept') }}</span><span class="text-success">{{ money($refundable) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.remaining_amount') }}</span><span class="fw-semibold">{{ money($owed) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('app.vat') }}</span><span>{{ qty((float) $order->vat_rate) }}%</span></li>
            </ul>
            <div class="card-body small text-muted">{{ __('app.cn_help') }}</div>
        </div>
        @if($complaint)
        <div class="card mt-3 border-warning">
            <div class="card-header"><i class="bi bi-chat-square-text me-1"></i> {{ $complaint->number }}</div>
            <div class="card-body small">
                <div class="fw-semibold">{{ __('app.complaint_category_' . $complaint->category) }}</div>
                <div>{{ $complaint->description }}</div>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-8 order-lg-1">
        <form action="{{ route("orders.credit-notes.store", $order) }}" method="POST" class="card">
            @csrf
            @if($complaint)<input type="hidden" name="complaint_id" value="{{ $complaint->id }}">@endif
            <div class="card-body">
                @if($creditable <= 0)
                    <div class="alert alert-warning mb-0">{{ __('app.cn_nothing_to_credit') }}</div>
                @else
                <fieldset class="mb-3">
                    <legend class="form-label fs-6">{{ __('app.cn_settlement') }}</legend>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="border rounded p-3 d-block h-100 {{ $refundable <= 0 ? "opacity-50" : "" }}">
                                <input class="form-check-input me-1" type="radio" name="settlement" value="refund" @checked(old("settlement", $refundable > 0 ? "refund" : "deduct") === "refund") @disabled($refundable <= 0)>
                                <span class="fw-semibold">{{ __('app.cn_settlement_refund') }}</span>
                                <span class="d-block small text-muted">{{ __('app.cn_settlement_refund_help', ["amount" => money(min($refundable, $creditable))]) }}</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="border rounded p-3 d-block h-100 {{ $owed <= 0 ? "opacity-50" : "" }}">
                                <input class="form-check-input me-1" type="radio" name="settlement" value="deduct" @checked(old("settlement", $refundable > 0 ? "refund" : "deduct") === "deduct") @disabled($owed <= 0)>
                                <span class="fw-semibold">{{ __('app.cn_settlement_deduct') }}</span>
                                <span class="d-block small text-muted">{{ __('app.cn_settlement_deduct_help', ["amount" => money($owed)]) }}</span>
                            </label>
                        </div>
                    </div>
                    @error("settlement") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </fieldset>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="amount">{{ __('app.cn_amount') }}</label>
                        <div class="input-group">
                            <input type="number" id="amount" name="amount" step="0.01" min="1" class="form-control @error("amount") is-invalid @enderror" value="{{ old("amount") }}" required>
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            @error("amount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text">{{ __('app.cn_amount_help') }}</div>
                    </div>
                    <div class="col-md-4" id="methodBox">
                        <label class="form-label" for="refund_method">{{ __('app.cn_refund_method') }}</label>
                        <select id="refund_method" name="refund_method" class="form-select @error("refund_method") is-invalid @enderror">
                            @foreach($methods as $m)
                                <option value="{{ $m }}" @selected(old("refund_method", "cash") === $m)>{{ __('app.' . $m) }}</option>
                            @endforeach
                        </select>
                        @error("refund_method") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="issued_at">{{ __('app.date') }}</label>
                        <input type="date" id="issued_at" name="issued_at" class="form-control @error("issued_at") is-invalid @enderror" value="{{ old("issued_at", now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                        @error("issued_at") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="reason">{{ __('app.reason') }}</label>
                        <select id="reason" name="reason" class="form-select" required>
                            @foreach(\App\Models\CreditNote::REASONS as $r)
                                <option value="{{ $r }}" @selected(old("reason", $complaint ? "complaint" : null) === $r)>{{ __('app.cn_reason_' . $r) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">{{ __('app.description') }}</label>
                        <textarea id="description" name="description" rows="2" maxlength="1000" class="form-control @error("description") is-invalid @enderror" required placeholder="{{ __('app.cn_description_placeholder') }}">{{ old("description", $complaint ? $complaint->number . ": " . $complaint->description : "") }}</textarea>
                        @error("description") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                @endif
            </div>
            <div class="card-footer d-flex justify-content-between bg-transparent">
                <a href="{{ route("orders.show", $order) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                @if($creditable > 0)
                <button type="submit" class="btn btn-danger" data-confirm="{{ __('app.cn_confirm') }}"><i class="bi bi-file-earmark-minus"></i> {{ __('app.cn_issue') }}</button>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection

@php($cnLimits = ["refund" => min($refundable, $creditable), "deduct" => min($owed, $creditable)])
@push("scripts")
    <x-page-data name="credit-note-form" :data="['limits' => $cnLimits]"/>
    @vite("resources/js/pages/credit-note-form.js")
@endpush
