{{-- Excel / PDF download of the report on screen, keeping its filters (same URL + ?format=…). --}}
<div {{ $attributes->merge(["class" => "btn-group"]) }}>
    <a href="{{ request()->fullUrlWithQuery(["format" => "xlsx"]) }}" class="btn btn-outline-success" title="{{ __('app.export_excel') }}">
        <i class="bi bi-file-earmark-excel"></i> Excel
    </a>
    <a href="{{ request()->fullUrlWithQuery(["format" => "pdf"]) }}" class="btn btn-outline-danger" title="{{ __('app.download_pdf') }}">
        <i class="bi bi-file-earmark-pdf"></i> PDF
    </a>
</div>
