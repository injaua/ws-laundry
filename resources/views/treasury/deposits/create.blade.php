@extends("layouts.admin")
@section("title", __('app.new_bank_deposit'))
@section("page_title", __('app.new_bank_deposit'))
@section("page_subtitle", __('app.bank_deposit_subtitle'))

@section("content")
@include("treasury._tabs")
<form method="POST" action="{{ route('treasury.deposits.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <label class="form-label">{{ __('app.date') }}</label>
                    <input type="date" name="deposited_at" value="{{ old('deposited_at', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control mb-2 @error('deposited_at') is-invalid @enderror" required>
                    @error('deposited_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <label class="form-label">{{ __('app.deposit_slip') }}</label>
                    <input type="text" name="slip_number" maxlength="40" value="{{ old('slip_number') }}" class="form-control mb-2 text-uppercase" placeholder="{{ __('app.deposit_slip_placeholder') }}">
                    <label class="form-label">{{ __('app.deposit_cash_amount') }}</label>
                    <div class="input-group mb-1">
                        <input type="number" name="cash_amount" min="0" step="1" value="{{ old('cash_amount', 0) }}" class="form-control js-dep-cash @error('cash_amount') is-invalid @enderror">
                        <span class="input-group-text">{{ currency_symbol() }}</span>
                        @error('cash_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    @if($cashBalance !== null)<div class="form-text mb-2">{{ __('app.cash_ledger_balance', ['amount' => money($cashBalance)]) }}</div>@endif
                    <label class="form-label">{{ __('app.notes') }}</label>
                    <input type="text" name="note" maxlength="255" value="{{ old('note') }}" class="form-control mb-3">
                    <div class="d-flex justify-content-between fs-5 mb-3"><span>{{ __('app.total') }}</span><span class="fw-bold js-dep-total">{{ money(0) }}</span></div>
                    @error('cheque_ids') <div class="alert alert-danger small py-2">{{ $message }}</div> @enderror
                    <button class="btn btn-primary w-100"><i class="bi bi-bank"></i> {{ __('app.record_bank_deposit') }}</button>
                    <div class="form-text">{{ __('app.bank_deposit_help') }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span>{{ __('app.cheques_in_hand') }}</span>
                    @if($waiting > $cheques->count())<span class="small text-muted">{{ __('app.cheques_still_in_till', ['count' => $waiting - $cheques->count()]) }}</span>@endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th></th><th>{{ __('app.cheque') }}</th><th>{{ __('app.customer') }}</th><th>{{ __('app.cheque_date') }}</th><th class="text-end">{{ __('app.amount') }}</th></tr></thead>
                        <tbody>
                        @forelse($cheques as $cheque)
                            @php($early = $cheque->isPostDated())
                            <tr class="{{ $early ? 'text-muted' : '' }}">
                                <td><input type="checkbox" class="form-check-input js-dep-cheque" name="cheque_ids[]" value="{{ $cheque->id }}" data-amount="{{ (float) $cheque->amount }}" @checked(in_array($cheque->id, old('cheque_ids', []))) @disabled($early) aria-label="{{ $cheque->label() }}"></td>
                                <td>{{ $cheque->label() }}</td>
                                <td>{{ $cheque->customer?->name }}</td>
                                <td>{{ $cheque->cheque_date->format(date_fmt()) }} @if($early)<span class="badge bg-warning text-dark">{{ __('app.post_dated') }}</span>@endif</td>
                                <td class="text-end">{{ num($cheque->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">{{ __('app.no_cheques_to_deposit') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push("scripts")
    @vite("resources/js/pages/deposit-form.js")
@endpush
