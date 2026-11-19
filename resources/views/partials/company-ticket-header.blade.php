{{-- Company identity on thermal tickets (Admin → System settings → General). --}}
@php($sysCompany = \App\Support\SystemSettings::all())
<h2>{{ \App\Support\SystemSettings::legalName() }}</h2>
@if($sysCompany['company_nuit'] !== '')<p>{{ __('app.nit') }}: {{ $sysCompany['company_nuit'] }}</p>@endif
@if($sysCompany['company_address'] !== '')<p>{{ $sysCompany['company_address'] }}</p>@endif
@if($sysCompany['company_phone'] !== '' || $sysCompany['company_email'] !== '')<p>{{ collect([$sysCompany['company_phone'], $sysCompany['company_email']])->filter()->implode(' · ') }}</p>@endif
