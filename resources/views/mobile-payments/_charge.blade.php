{{-- Push payment (M-Pesa / e-Mola) for an order. Params: $order, optional $job (courier job), $compact --}}
{{-- $mmProviders, $mmRemaining, $mmPhone, $mmGuess, $mmRecent: App\View\Composers\MobileChargeComposer --}}
@if($mmProviders !== [] && ($mmRemaining > 0 || $mmRecent->isNotEmpty()))
<div class="card mb-3 border-success-subtle">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-phone me-1"></i> {{ __('app.mm_charge_title') }}</span>
        @foreach($mmProviders as $p)
            @if(\App\Services\MobileMoney\MobileMoneySettings::mode($p) !== 'live')
                <span class="badge bg-warning text-dark" title="{{ __('app.mm_mode_' . \App\Services\MobileMoney\MobileMoneySettings::mode($p)) }}">{{ __('app.' . $p) }}: {{ __('app.mm_mode_' . \App\Services\MobileMoney\MobileMoneySettings::mode($p)) }}</span>
            @endif
        @endforeach
    </div>
    <div class="card-body">
        @if($mmRemaining > 0)
        <form method="POST" action="{{ route('mobile-payments.store') }}" class="row g-2">
            @csrf
            <input type="hidden" name="order_id" value="{{ $order->id }}">
            @isset($job)<input type="hidden" name="courier_job_id" value="{{ $job->id }}">@endisset
            <div class="col-sm-4">
                <select name="provider" class="form-select form-select-sm" required>
                    @foreach($mmProviders as $p)
                        <option value="{{ $p }}" @selected(old('provider', $mmGuess) === $p)>{{ __('app.' . $p) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4">
                <input type="tel" name="phone" class="form-control form-control-sm @error('phone') is-invalid @enderror" value="{{ old('phone', $mmPhone) }}" placeholder="84 000 0000" required>
                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <input type="number" name="amount" min="1" max="{{ $mmRemaining }}" step="0.01" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $mmRemaining) }}" @isset($job) readonly @endisset>
                    <span class="input-group-text">{{ currency_symbol() }}</span>
                </div>
                @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            @error('provider') <div class="col-12 text-danger small">{{ $message }}</div> @enderror
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-success btn-sm flex-grow-1"><i class="bi bi-send"></i> {{ __('app.mm_send_request') }}</button>
                @if(!isset($job) && !auth()->user()->isCourier())
                    <button type="submit" form="mm-link-{{ $order->id }}" class="btn btn-outline-success btn-sm" title="{{ __('app.mm_send_link_help') }}"><i class="bi bi-whatsapp"></i> {{ __('app.mm_send_link') }}</button>
                @endif
            </div>
            <div class="col-12 form-text mt-0">{{ __('app.mm_charge_help') }}</div>
        </form>
        @if(!isset($job) && !auth()->user()->isCourier())
            <form id="mm-link-{{ $order->id }}" method="POST" action="{{ route('mobile-payments.send-link', $order) }}">@csrf</form>
        @endif
        @endif

        @if($mmRecent->isNotEmpty())
            <ul class="list-unstyled small mb-0 {{ $mmRemaining > 0 ? 'mt-2 border-top pt-2' : '' }}">
                @foreach($mmRecent as $tx)
                    <li class="d-flex justify-content-between">
                        <a href="{{ route('mobile-payments.show', $tx) }}">{{ __('app.' . $tx->provider) }} · {{ money((float) $tx->amount) }} · {{ $tx->created_at->format('d/m H:i') }}</a>
                        <span class="badge bg-{{ $tx->statusColor() }}">{{ __('app.mm_status_' . $tx->status) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endif
