{{-- Sidebar · Resources (admin / owner; the accountant sees suppliers and purchases), accounting and finance. --}}
@if(auth()->user()->can("manage-finance"))
@php($resourcesActive = request()->routeIs("resources.*"))
<a class="nav-link d-flex justify-content-between align-items-center {{ $resourcesActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseResources" role="button" aria-expanded="{{ $resourcesActive ? "true" : "false" }}" aria-controls="collapseResources">
    <span><i class="bi bi-inboxes"></i> {{ __('app.resources') }}</span>
    <i class="bi bi-chevron-down chev"></i>
</a>
<div class="collapse {{ $resourcesActive ? "show" : "" }}" id="collapseResources">
    @if(auth()->user()->can("manage-store"))
    <a href="{{ route("resources.equipments.index") }}" class="nav-link ms-3 {{ request()->routeIs("resources.equipments.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-gear-wide-connected"></i> {{ __('app.equipments') }}
    </a>
    <a href="{{ route("resources.items.index", ["kind" => "product"]) }}" class="nav-link ms-3 {{ request()->routeIs("resources.items.*") && !in_array(request("kind", "product"), ["material", "part"]) ? "active" : "" }} nav-sub">
        <i class="bi bi-droplet"></i> {{ __('app.products') }}
    </a>
    <a href="{{ route("resources.items.index", ["kind" => "material"]) }}" class="nav-link ms-3 {{ request()->routeIs("resources.items.*") && request("kind") == "material" ? "active" : "" }} nav-sub">
        <i class="bi bi-bag"></i> {{ __('app.materials') }}
    </a>
    <a href="{{ route("resources.lots.index") }}" class="nav-link ms-3 {{ request()->routeIs("resources.lots.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-boxes"></i> {{ __('app.lots') }}
    </a>
    @endif
    <a href="{{ route("resources.suppliers.index") }}" class="nav-link ms-3 {{ request()->routeIs("resources.suppliers.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-truck"></i> {{ __('app.suppliers') }}
    </a>
    <a href="{{ route("resources.purchases.index") }}" class="nav-link ms-3 {{ request()->routeIs("resources.purchases.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-bag-plus"></i> {{ __('app.purchase_orders') }}
    </a>
    @if(auth()->user()->can("manage-store"))
    <a href="{{ route("resources.maintenance.index") }}" class="nav-link ms-3 {{ request()->routeIs("resources.maintenance.*") || (request()->routeIs("resources.items.*") && request("kind") == "part") ? "active" : "" }} nav-sub">
        <i class="bi bi-tools"></i> {{ __('app.maintenance') }}
    </a>
    @endif
</div>
@endif

@if(auth()->user()->can("manage-finance"))
@php($accountingActive = request()->routeIs("accounting.*") || request()->routeIs("receivables.*") || request()->routeIs("invoices.*"))
<a class="nav-link d-flex justify-content-between align-items-center {{ $accountingActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseAccounting" role="button" aria-expanded="{{ $accountingActive ? "true" : "false" }}" aria-controls="collapseAccounting">
    <span><i class="bi bi-journal-text"></i> {{ __('app.accounting') }}</span>
    <i class="bi bi-chevron-down chev"></i>
</a>
<div class="collapse {{ $accountingActive ? "show" : "" }}" id="collapseAccounting">
    <a href="{{ route("receivables.index") }}" class="nav-link ms-3 {{ request()->routeIs("receivables.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-cash-coin"></i> {{ __('app.receivables') }}
    </a>
    <a href="{{ route("invoices.index") }}" class="nav-link ms-3 {{ request()->routeIs("invoices.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-files"></i> {{ __('app.invoices') }}
    </a>
    <a href="{{ route("accounting.accounts.index") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.accounts.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-diagram-3"></i> {{ __('app.chart_of_accounts') }}
    </a>
    <a href="{{ route("accounting.journal-entries.index") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.journal-entries.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-journal-plus"></i> {{ __('app.journal') }}
    </a>
    <a href="{{ route("accounting.trial-balance") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.trial-balance") ? "active" : "" }} nav-sub">
        <i class="bi bi-file-earmark-text"></i> {{ __('app.trial_balance') }}
    </a>
    <a href="{{ route("accounting.balance-sheet") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.balance-sheet") ? "active" : "" }} nav-sub">
        <i class="bi bi-clipboard-data"></i> {{ __('app.balance_sheet') }}
    </a>
    <a href="{{ route("accounting.income-statement") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.income-statement") ? "active" : "" }} nav-sub">
        <i class="bi bi-graph-up"></i> {{ __('app.income_statement') }}
    </a>
    <a href="{{ route("accounting.vat-settlement") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.vat-settlement*") ? "active" : "" }} nav-sub">
        <i class="bi bi-percent"></i> {{ __('app.vat_settlement') }}
    </a>
    <a href="{{ route("accounting.period-close") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.period-close*") ? "active" : "" }} nav-sub">
        <i class="bi bi-shield-lock"></i> {{ __('app.accounting_close') }}
    </a>
</div>
@endif
