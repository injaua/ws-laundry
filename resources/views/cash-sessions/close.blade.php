@extends("layouts.admin")
@section("title", __('app.close_till'))
@section("page_title", __('app.close_till') . " " . $session->label())

@section("content")
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-lock me-1"></i> {{ __('app.close_till') }} — {{ $session->user->name }}</div>
            <div class="card-body">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-eye-slash me-1"></i> {{ __('app.blind_count_help') }}
                </div>
                @error("counted") <div class="alert alert-danger small">{{ $message }}</div> @enderror
                <form action="{{ route("cash-sessions.close", $session) }}" method="POST" data-confirm="{{ __('app.close_till_confirm') }}">
                    @csrf
                    @foreach(\App\Models\Payment::METHODS as $method)
                    <div class="row g-2 align-items-center mb-2">
                        <label class="col-5 col-form-label" for="counted_{{ $method }}">
                            {{ __('app.' . $method) }}
                            @if($method === "cash")<div class="form-text mt-0">{{ __('app.count_cash_help') }}</div>@endif
                            @if(\App\Models\Payment::isMobile($method))<div class="form-text mt-0">{{ __('app.verify_mobile_help') }}</div>@endif
                            @if($method === "transfer")<div class="form-text mt-0">{{ __('app.verify_transfer_help') }}</div>@endif
                            @if($method === "cheque")<div class="form-text mt-0">{{ __('app.count_cheques_help') }}</div>@endif
                        </label>
                        @if($method !== "cash")
                            @php($refRows = $references[$method] ?? collect())
                            @if($refRows->isNotEmpty())
                            <div class="col-12 order-last">
                                <details class="small mb-1">
                                    <summary>{{ __($method === 'cheque' ? 'app.cheques_to_count' : 'app.mobile_refs_to_verify', ['count' => $refRows->count()]) }}</summary>
                                    <ul class="mb-0 font-monospace">
                                        @foreach($refRows as $row)
                                            <li>{{ $row }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            </div>
                            @endif
                        @endif
                        <div class="col-7">
                            <div class="input-group">
                                <input type="number" id="counted_{{ $method }}" name="counted[{{ $method }}]" class="form-control @error("counted.$method") is-invalid @enderror" value="{{ old("counted.$method") }}" min="0" step="0.01" required>
                                <span class="input-group-text">{{ currency_symbol() }}</span>
                                @error("counted.$method") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    @endforeach
                    <div class="mb-3 mt-3">
                        <label class="form-label">{{ __('app.closing_note') }}</label>
                        <textarea name="closing_note" rows="2" class="form-control @error("closing_note") is-invalid @enderror" placeholder="{{ __('app.closing_note_placeholder') }}">{{ old("closing_note") }}</textarea>
                        @error("closing_note") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route("cash-sessions.show", $session) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                        <button type="submit" class="btn btn-danger flex-fill"><i class="bi bi-lock"></i> {{ __('app.close_till') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
