{{-- Sidebar · Day-to-day work: orders, customers, till, production, logistics, treasury, catalogue. --}}
        <div class="nav-section">{{ __('app.main_menu') }}</div>
<a href="{{ route("dashboard") }}" class="nav-link {{ request()->routeIs("dashboard") ? "active" : "" }}">
    <i class="bi bi-speedometer2"></i> {{ __('app.dashboard') }}
</a>

{{-- Counter staff work here; the accountant only consults orders, customers, tills and payments. --}}
@php($staff = auth()->user()->can("work-counter"))
@if(auth()->user()->can("view-records"))
<a href="{{ route("orders.index") }}" class="nav-link {{ request()->routeIs("orders.*") ? "active" : "" }}">
    <i class="bi bi-receipt"></i> {{ __('app.transactions') }}
</a>
@endif
@if($staff)
<a href="{{ route("tracking.index") }}" class="nav-link {{ request()->routeIs("tracking.*") ? "active" : "" }}">
    <i class="bi bi-upc-scan"></i> {{ __('app.piece_tracking') }}
</a>
<a href="{{ route("complaints.index") }}" class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs("complaints.*") ? "active" : "" }}">
    <span><i class="bi bi-chat-square-text"></i> {{ __('app.complaints') }}</span>
    @if($navBadges['complaints'])<span class="badge rounded-pill bg-danger nav-badge">{{ $navBadges['complaints'] }}</span>@endif
</a>
<a href="{{ route("unclaimed.index") }}" class="nav-link {{ request()->routeIs("unclaimed.*") ? "active" : "" }}">
    <i class="bi bi-hourglass-split"></i> {{ __('app.unclaimed') }}
</a>
@endif
@if(auth()->user()->can("viewAny", \App\Models\CreditNote::class))
<a href="{{ route("credit-notes.index") }}" class="nav-link {{ request()->routeIs("credit-notes.*") ? "active" : "" }}">
    <i class="bi bi-file-earmark-minus"></i> {{ __('app.credit_notes') }}
</a>
@endif
@if(auth()->user()->can("view-records"))
<a href="{{ route("customers.index") }}" class="nav-link {{ request()->routeIs("customers.*") ? "active" : "" }}">
    <i class="bi bi-people"></i> {{ __('app.customers') }}
</a>
@endif
@if(auth()->user()->isAccountant())
<a href="{{ route("cash-sessions.index") }}" class="nav-link {{ request()->routeIs("cash-sessions.*") ? "active" : "" }}">
    <i class="bi bi-safe"></i> {{ __('app.till_history') }}
</a>
@endif
@if($staff)
<a href="{{ route("cash-sessions.current") }}" class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs("cash-sessions.*") ? "active" : "" }}">
    <span><i class="bi bi-safe"></i> {{ __('app.till') }}</span>
    <span class="badge rounded-pill bg-{{ $navBadges['till'] ? "success" : "secondary" }} nav-badge">{{ $navBadges['till'] ? __('app.till_status_open') : __('app.till_status_closed') }}</span>
</a>
<a href="{{ route("production.board") }}" class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs("production.*") ? "active" : "" }}">
    <span><i class="bi bi-kanban"></i> {{ __('app.production') }}</span>
    @if($navBadges['late_orders'])<span class="badge rounded-pill bg-danger nav-badge" title="{{ __('app.late_orders') }}">{{ $navBadges['late_orders'] }}</span>@endif
</a>
@endif
@if(auth()->user()->can("view-mobile-payments"))
<a href="{{ route("mobile-payments.index") }}" class="nav-link {{ request()->routeIs("mobile-payments.*") ? "active" : "" }}">
    <i class="bi bi-phone"></i> {{ __('app.mobile_payments') }}
</a>
@endif
@if(auth()->user()->can("manage-finance"))
<a href="{{ route("treasury.cheques") }}" class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs("treasury.*") || request()->routeIs("wallet-reconciliation.*") ? "active" : "" }}">
    <span><i class="bi bi-bank"></i> {{ __('app.treasury') }}</span>
    @if($navBadges['cheques_in_hand'])<span class="badge rounded-pill bg-primary nav-badge" title="{{ __('app.cheque_status_in_hand') }}">{{ $navBadges['cheques_in_hand'] }}</span>@endif
</a>
@endif
@if($staff)
<a href="{{ route("logistics.index") }}" class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs("logistics.*") || request()->routeIs("delivery-zones.*") ? "active" : "" }}">
    <span><i class="bi bi-truck"></i> {{ __('app.logistics') }}</span>
    @if($navBadges['pickup_requests'])<span class="badge rounded-pill bg-warning text-dark nav-badge">{{ $navBadges['pickup_requests'] }}</span>@endif
</a>
@endif

@if(auth()->user()->can("manage-catalog"))
<a href="{{ route("services.index") }}" class="nav-link {{ request()->routeIs("services.*") ? "active" : "" }}">
    <i class="bi bi-gear"></i> {{ __('app.services') }}
</a>
@endif

@if(auth()->user()->can("view-reports"))
<a href="{{ route("management.index") }}" class="nav-link {{ request()->routeIs("management.*") ? "active" : "" }}">
    <i class="bi bi-speedometer2"></i> {{ __('app.management_indicators') }}
</a>
@php($reportsActive = request()->routeIs("reports") || request()->routeIs("reports.profit-loss") || request()->routeIs("reports.payment-methods"))
<a class="nav-link d-flex justify-content-between align-items-center {{ $reportsActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseReports" role="button" aria-expanded="{{ $reportsActive ? "true" : "false" }}" aria-controls="collapseReports">
    <span><i class="bi bi-bar-chart"></i> {{ __('app.reports') }}</span>
    <i class="bi bi-chevron-down chev"></i>
</a>
<div class="collapse {{ $reportsActive ? "show" : "" }}" id="collapseReports">
    <a href="{{ route("reports") }}" class="nav-link ms-3 {{ request()->routeIs("reports") ? "active" : "" }} nav-sub">
        <i class="bi bi-clipboard-data"></i> {{ __('app.reports') }}
    </a>
    <a href="{{ route("reports.profit-loss") }}" class="nav-link ms-3 {{ request()->routeIs("reports.profit-loss") ? "active" : "" }} nav-sub">
        <i class="bi bi-calculator"></i> {{ __('app.profit_loss') }}
    </a>
    <a href="{{ route("reports.payment-methods") }}" class="nav-link ms-3 {{ request()->routeIs("reports.payment-methods") ? "active" : "" }} nav-sub">
        <i class="bi bi-credit-card"></i> {{ __('app.payment_methods_report') }}
    </a>
</div>
@endif

@if(auth()->user()->can("manage-catalog"))
<a href="{{ route("packages.index") }}" class="nav-link {{ request()->routeIs("packages.*") ? "active" : "" }}">
    <i class="bi bi-box-seam"></i> {{ __('app.packages') }}
</a>
@endif

@if(auth()->user()->can("manage-store"))
<a href="{{ route("promotions.index") }}" class="nav-link {{ request()->routeIs("promotions.*") ? "active" : "" }}">
    <i class="bi bi-megaphone"></i> {{ __('app.promotions') }}
</a>
@endif

@if(auth()->user()->can("manage-finance"))
@php($expensesActive = request()->routeIs("expenses.*") || request()->routeIs("expense-categories.*"))
<a class="nav-link d-flex justify-content-between align-items-center {{ $expensesActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseExpenses" role="button" aria-expanded="{{ $expensesActive ? "true" : "false" }}" aria-controls="collapseExpenses">
    <span><i class="bi bi-cash-stack"></i> {{ __('app.expenses') }}</span>
    <i class="bi bi-chevron-down chev"></i>
</a>
<div class="collapse {{ $expensesActive ? "show" : "" }}" id="collapseExpenses">
    <a href="{{ route("expenses.index") }}" class="nav-link ms-3 {{ request()->routeIs("expenses.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-cash-stack"></i> {{ __('app.expenses') }}
    </a>
    <a href="{{ route("expense-categories.index") }}" class="nav-link ms-3 {{ request()->routeIs("expense-categories.*") ? "active" : "" }} nav-sub">
        <i class="bi bi-tags"></i> {{ __('app.expense_categories') }}
    </a>
</div>
@endif
