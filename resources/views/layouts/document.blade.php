@php
    $company = \App\Support\SystemSettings::all();
    $companyName = \App\Support\SystemSettings::legalName();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <link rel="icon" href="{{ \App\Support\SystemSettings::faviconUrl() }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield("title")</title>
    @vite(["resources/css/document.css", "resources/js/print.js"])
</head>
<body>
    <div class="toolbar">
        <button type="button" data-print>&#128424; {{ __('app.print') }}</button>
        @yield("toolbar")
    </div>
    <div class="doc">
        <div class="head">
            <div>
                <img class="doc-logo" src="{{ \App\Support\SystemSettings::logoUrl() }}" alt="" data-hide-broken>
                <h1>{{ $companyName }}</h1>
                @if(!empty($company["company_nuit"]))<div>{{ __('app.nit') }}: {{ $company["company_nuit"] }}</div>@endif
                @if(!empty($company["company_address"]))<div class="muted">{{ $company["company_address"] }}</div>@endif
                <div class="muted">
                    {{ $company["company_phone"] ?? "" }}
                    @if(!empty($company["company_phone"]) && !empty($company["company_email"])) · @endif
                    {{ $company["company_email"] ?? "" }}
                </div>
            </div>
            <div class="meta">
                <div class="doc-type">@yield("doc_type")</div>
                @yield("doc_meta")
            </div>
        </div>

        @yield("content")

        @if(!empty($company["invoice_bank_details"]))
            <div class="note"><div class="label">{{ __('app.invoice_bank_details') }}</div>{{ $company["invoice_bank_details"] }}</div>
        @endif

        <div class="foot">
            @if(($company["document_footer"] ?? "") !== "")<div>{!! nl2br(e($company["document_footer"])) !!}</div>@endif
            {{ $companyName }}@if(($company["company_share_capital"] ?? "") !== "") · {{ __('app.share_capital') }}: {{ $company["company_share_capital"] }}@endif
            · {{ __('app.document_generated_on', ["date" => now()->format(datetime_fmt())]) }}
        </div>
    </div>
</body>
</html>
