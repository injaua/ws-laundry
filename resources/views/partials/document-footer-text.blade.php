{{-- Footer text for printed documents; the default thanks the customer. --}}
@php($sysFooter = \App\Support\SystemSettings::get('document_footer'))
@if($sysFooter !== ''){!! nl2br(e($sysFooter)) !!}@else{{ __('app.document_footer_default') }}@endif
@if(\App\Support\SystemSettings::get('company_share_capital') !== '')<br>{{ __('app.share_capital') }}: {{ \App\Support\SystemSettings::get('company_share_capital') }}@endif
