@extends("layouts.admin")
@section("title", __('app.system_settings_general'))
@section("page_title", __('app.system_settings') . ' · ' . __('app.system_settings_general'))

@section("content")
<form action="{{ route('admin.system-settings.general.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="bi bi-building me-1"></i> {{ __('app.company_identity') }}</div>
                <div class="card-body">
                    <p class="small text-muted">{{ __('app.company_identity_hint') }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="company_name">{{ __('app.company_trade_name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="company_name" maxlength="100" required class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name', $settings['company_name']) }}">
                            <div class="form-text">{{ __('app.company_trade_name_help') }}</div>
                            @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_legal_name">{{ __('app.legal_name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="company_legal_name" id="company_legal_name" maxlength="150" required class="form-control @error('company_legal_name') is-invalid @enderror" value="{{ old('company_legal_name', $settings['company_legal_name']) }}">
                            <div class="form-text">{{ __('app.company_legal_name_help') }}</div>
                            @error('company_legal_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="company_nuit">{{ __('app.nit') }}</label>
                            <input type="text" name="company_nuit" id="company_nuit" maxlength="9" inputmode="numeric" class="form-control @error('company_nuit') is-invalid @enderror" value="{{ old('company_nuit', $settings['company_nuit']) }}">
                            @error('company_nuit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="company_phone">{{ __('app.phone') }}</label>
                            <input type="text" name="company_phone" id="company_phone" maxlength="50" class="form-control @error('company_phone') is-invalid @enderror" value="{{ old('company_phone', $settings['company_phone']) }}">
                            @error('company_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="company_share_capital">{{ __('app.share_capital') }}</label>
                            <input type="text" name="company_share_capital" id="company_share_capital" maxlength="50" class="form-control @error('company_share_capital') is-invalid @enderror" value="{{ old('company_share_capital', $settings['company_share_capital']) }}" placeholder="{{ money(100000) }}">
                            @error('company_share_capital') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_email">{{ __('app.email') }}</label>
                            <input type="email" name="company_email" id="company_email" maxlength="150" class="form-control @error('company_email') is-invalid @enderror" value="{{ old('company_email', $settings['company_email']) }}">
                            @error('company_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_address">{{ __('app.address') }}</label>
                            <input type="text" name="company_address" id="company_address" maxlength="255" class="form-control @error('company_address') is-invalid @enderror" value="{{ old('company_address', $settings['company_address']) }}">
                            @error('company_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="invoice_bank_details">{{ __('app.invoice_bank_details') }}</label>
                            <textarea name="invoice_bank_details" id="invoice_bank_details" rows="2" maxlength="500" class="form-control @error('invoice_bank_details') is-invalid @enderror" placeholder="{{ __('app.invoice_bank_details_hint') }}">{{ old('invoice_bank_details', $settings['invoice_bank_details']) }}</textarea>
                            @error('invoice_bank_details') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="document_footer">{{ __('app.document_footer') }}</label>
                            <textarea name="document_footer" id="document_footer" rows="2" maxlength="500" class="form-control @error('document_footer') is-invalid @enderror" placeholder="{{ __('app.document_footer_default') }}">{{ old('document_footer', $settings['document_footer']) }}</textarea>
                            <div class="form-text">{{ __('app.document_footer_help') }}</div>
                            @error('document_footer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-image me-1"></i> {{ __('app.branding') }}</div>
                <div class="card-body">
                    @foreach([
                        'brand_logo' => [__('app.logo'), __('app.logo_help'), \App\Support\SystemSettings::logoUrl(), '.png,.jpg,.jpeg,.webp', '#fff'],
                        'brand_logo_dark' => [__('app.logo_dark'), __('app.logo_dark_help'), \App\Support\SystemSettings::logoDarkUrl(), '.png,.jpg,.jpeg,.webp', '#1e293b'],
                        'brand_favicon' => [__('app.favicon'), __('app.favicon_help'), \App\Support\SystemSettings::faviconUrl(), '.png,.ico', '#fff'],
                    ] as $key => [$label, $help, $url, $accept, $bg])
                        <div class="mb-4">
                            <label class="form-label" for="{{ $key }}">{{ $label }}</label>
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="border rounded d-flex align-items-center justify-content-center" style="width:120px;height:64px;background:{{ $bg }}">
                                    <img class="mw-110px mh-56px img-contain" src="{{ $url }}" alt="" data-hide-broken>
                                </div>
                                @if($settings[$key] !== '')
                                    <div class="form-check small">
                                        <input class="form-check-input" type="checkbox" name="remove[]" value="{{ $key }}" id="remove_{{ $key }}">
                                        <label class="form-check-label" for="remove_{{ $key }}">{{ __('app.remove_restore_default') }}</label>
                                    </div>
                                @else
                                    <span class="small text-muted">{{ __('app.using_default_image') }}</span>
                                @endif
                            </div>
                            <input type="file" name="{{ $key }}" id="{{ $key }}" accept="{{ $accept }}" class="form-control form-control-sm @error($key) is-invalid @enderror">
                            <div class="form-text">{{ $help }}</div>
                            @error($key) <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="text-end mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.save') }}</button>
    </div>
</form>
@endsection
