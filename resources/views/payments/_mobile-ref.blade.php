{{-- Fields that depend on the payment method chosen in the same form:
     transfer / mobile wallets -> reference (mandatory, unique); wallets also the paying number;
     POS -> terminal slip number (mandatory); cheque -> number, bank, date (post-dated allowed), drawer;
     cash -> amount handed over and change.
     Params: $sm (bool, small inputs), $ref, $phone, $tender (bool, show the change helper, default on),
     $tendered (amount handed over when editing), $cheque (existing Cheque when editing) --}}
@php
    $sm = $sm ?? false;
    $tender = $tender ?? true;
    $tendered = $tendered ?? null;
    $cheque = $cheque ?? null;
    $inputSm = $sm ? 'form-control-sm' : '';
    $refLabels = ["pos" => __("app.pos_ref"), "transfer" => __("app.transfer_ref"), "cheque" => __("app.cheque_number"), "mobile" => __("app.transaction_ref")];
@endphp
<div class="js-pay-extra {{ $sm ? 'mt-1' : 'mb-3' }}">
    <div class="row g-2 js-for d-none" data-methods="pos,transfer,cheque,mpesa,emola,mkesh">
        <div class="col-7 js-ref-col">
            @unless($sm)<label class="form-label"><span class="js-ref-label">{{ __('app.transaction_ref') }}</span> <span class="text-danger">*</span></label>@endunless
            <input type="text" name="transaction_ref" maxlength="40" autocomplete="off"
                class="form-control {{ $inputSm }} text-uppercase @error('transaction_ref') is-invalid @enderror"
                value="{{ old('transaction_ref', $ref ?? '') }}" placeholder="{{ __('app.transaction_ref_placeholder') }}"
                data-labels='@json($refLabels)'>
            @error('transaction_ref') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-5 js-for" data-methods="mpesa,emola,mkesh">
            @unless($sm)<label class="form-label">{{ __('app.payer_phone') }}</label>@endunless
            <input type="text" name="payer_phone" maxlength="20" class="form-control {{ $inputSm }} @error('payer_phone') is-invalid @enderror"
                value="{{ old('payer_phone', $phone ?? '') }}" placeholder="{{ $sm ? __('app.payer_phone') : '84 000 0000' }}">
        </div>
        <div class="col-5 js-for" data-methods="cheque">
            @unless($sm)<label class="form-label">{{ __('app.cheque_bank') }} <span class="text-danger">*</span></label>@endunless
            <input type="text" name="cheque_bank" maxlength="60" list="cheque-banks" class="form-control {{ $inputSm }} @error('cheque_bank') is-invalid @enderror"
                value="{{ old('cheque_bank', $cheque?->bank) }}" placeholder="{{ __('app.cheque_bank') }}">
            @error('cheque_bank') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-6 js-for" data-methods="cheque">
            @unless($sm)<label class="form-label">{{ __('app.cheque_date') }}</label>@endunless
            <input type="date" name="cheque_date" class="form-control {{ $inputSm }} @error('cheque_date') is-invalid @enderror"
                value="{{ old('cheque_date', $cheque?->cheque_date?->toDateString()) }}" title="{{ __('app.cheque_date') }}">
            @error('cheque_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-6 js-for" data-methods="cheque">
            @unless($sm)<label class="form-label">{{ __('app.cheque_drawer') }}</label>@endunless
            <input type="text" name="cheque_drawer" maxlength="120" class="form-control {{ $inputSm }}"
                value="{{ old('cheque_drawer', $cheque?->drawer) }}" placeholder="{{ __('app.cheque_drawer') }}">
        </div>
        @unless($sm)
            <div class="col-12 form-text mt-0 js-for" data-methods="mpesa,emola,mkesh">{{ __('app.transaction_ref_hint') }}</div>
            <div class="col-12 form-text mt-0 js-for" data-methods="pos">{{ __('app.pos_ref_hint') }}</div>
            <div class="col-12 form-text mt-0 js-for" data-methods="transfer">{{ __('app.transfer_ref_hint') }}</div>
            <div class="col-12 form-text mt-0 js-for" data-methods="cheque">{{ __('app.cheque_hint') }}</div>
        @endunless
    </div>
    @if($tender)
    <div class="row g-2 align-items-end js-for d-none" data-methods="cash">
        <div class="col-7">
            @unless($sm)<label class="form-label">{{ __('app.tendered') }}</label>@endunless
            <div class="input-group {{ $sm ? 'input-group-sm' : '' }}">
                <input type="number" name="tendered" min="0" step="0.01" class="form-control js-tendered @error('tendered') is-invalid @enderror" value="{{ old('tendered', $tendered) }}" placeholder="{{ $sm ? __('app.tendered') : __('app.tendered_placeholder') }}" title="{{ __('app.tendered') }}">
                <span class="input-group-text">{{ currency_symbol() }}</span>
                @error('tendered') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="col-5">
            <div class="small text-muted">{{ __('app.change_due') }}</div>
            <div class="{{ $sm ? '' : 'fs-5' }} fw-bold text-success js-change">—</div>
        </div>
    </div>
    @endif
</div>
@once
<datalist id="cheque-banks">
    @foreach(\App\Models\Cheque::BANKS as $bank)<option value="{{ $bank }}">@endforeach
</datalist>
@push("scripts")
    <x-page-data name="payment-fields" :data="['mobile' => \App\Models\Payment::MOBILE_METHODS, 'texts' => ['tenderedShort' => __('app.tendered_short')]]"/>
    @vite("resources/js/pages/payment-fields.js")
@endpush
@endonce
