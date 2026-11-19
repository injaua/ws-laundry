{{-- Personal data: copy for the customer and erasure on request (admin / owner). --}}
{{-- Personal data: copy for the customer and erasure on request --}}
@if(auth()->user()->isAdmin() || auth()->user()->isOwner())
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-person-lock me-1"></i> {{ __('app.personal_data') }}</div>
    <div class="card-body small">
        @if($customer->anonymized_at)
            <div class="alert alert-secondary py-2 mb-0"><i class="bi bi-incognito"></i> {{ __('app.privacy_anonymized_on', ['date' => $customer->anonymized_at->format(date_fmt())]) }}</div>
        @else
            <a href="{{ route('customers.privacy.export', $customer) }}" class="btn btn-sm btn-outline-secondary w-100 mb-2"><i class="bi bi-download"></i> {{ __('app.privacy_export') }}</a>
            @if($privacyBlockers)
                <div class="text-muted"><i class="bi bi-lock"></i> {{ __('app.privacy_cannot_anonymize') }}
                    <ul class="mb-0 ps-3">@foreach($privacyBlockers as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                </div>
            @else
                <button class="btn btn-sm btn-outline-danger w-100" data-bs-toggle="collapse" data-bs-target="#anonymize">{{ __('app.privacy_anonymize') }}</button>
                <form method="POST" action="{{ route('customers.privacy.anonymize', $customer) }}" class="collapse mt-2" id="anonymize">
                    @csrf
                    <p class="text-danger mb-2">{{ __('app.privacy_anonymize_warning') }}</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="confirm" value="1" id="anon_confirm" required>
                        <label class="form-check-label" for="anon_confirm">{{ __('app.privacy_anonymize_confirm') }}</label>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ __('app.password') }}" required autocomplete="current-password">
                        <button class="btn btn-danger">{{ __('app.privacy_anonymize') }}</button>
                    </div>
                    @error('password') <div class="text-danger small">{{ $message }}</div> @enderror
                </form>
            @endif
            @error('customer') <div class="text-danger mt-2">{{ $message }}</div> @enderror
        @endif
    </div>
</div>
@endif
