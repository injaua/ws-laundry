@php
    $company = \App\Support\SystemSettings::all();
    $companyName = \App\Support\SystemSettings::legalName();
    // $pdf: rendered by dompdf (App\Support\Documents\DocumentResponse) — no toolbar, no Vite,
    // a dompdf-friendly stylesheet and the logo embedded as a data: URI.
    $pdf = $pdf ?? false;
    $logo = $pdf ? \App\Support\SystemSettings::logoDataUri() : \App\Support\SystemSettings::logoUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>@yield("title")</title>
    @if($pdf)
        <link rel="stylesheet" href="{{ \App\Support\Documents\DocumentResponse::stylesheet() }}">
    @else
        <link rel="icon" href="{{ \App\Support\SystemSettings::faviconUrl() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @vite(["resources/css/document.css", "resources/js/print.js"])
    @endif
</head>
<body class="{{ $pdf ? 'pdf' : '' }}">
    @unless($pdf)
        <div class="toolbar">
            <button type="button" data-print>&#128424; {{ __('app.print') }}</button>
            @if(!empty($pdfAvailable))
                <a href="{{ request()->fullUrlWithQuery(["format" => "pdf"]) }}">&#11015; {{ __('app.download_pdf') }}</a>
            @endif
            @if(!empty($xlsxAvailable))
                <a href="{{ request()->fullUrlWithQuery(["format" => "xlsx"]) }}">&#11015; {{ __('app.export_excel') }}</a>
            @endif
            @yield("toolbar")
        </div>
    @endunless
    <div class="doc">
        <table class="head">
            <tr>
                <td>
                    @if($logo)<img class="doc-logo" src="{{ $logo }}" alt="" data-hide-broken>@endif
                    <h1>{{ $companyName }}</h1>
                    @if(!empty($company["company_nuit"]))<div>{{ __('app.nit') }}: {{ $company["company_nuit"] }}</div>@endif
                    @if(!empty($company["company_address"]))<div class="muted">{{ $company["company_address"] }}</div>@endif
                    <div class="muted">
                        {{ $company["company_phone"] ?? "" }}
                        @if(!empty($company["company_phone"]) && !empty($company["company_email"])) · @endif
                        {{ $company["company_email"] ?? "" }}
                    </div>
                </td>
                <td class="meta">
                    <div class="doc-type">@yield("doc_type")</div>
                    @yield("doc_meta")
                </td>
            </tr>
        </table>

        @yield("content")

        @unless(View::hasSection("no_bank_details"))
            @if(!empty($company["invoice_bank_details"]))
                <div class="note"><div class="label">{{ __('app.invoice_bank_details') }}</div>{{ $company["invoice_bank_details"] }}</div>
            @endif
        @endunless

        <div class="foot">
            @if(($company["document_footer"] ?? "") !== "")<div>{!! nl2br(e($company["document_footer"])) !!}</div>@endif
            {{ $companyName }}@if(($company["company_share_capital"] ?? "") !== "") · {{ __('app.share_capital') }}: {{ $company["company_share_capital"] }}@endif
            · {{ __('app.document_generated_on', ["date" => now()->format(datetime_fmt())]) }}
        </div>
    </div>
</body>
</html>
