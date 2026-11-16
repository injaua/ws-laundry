<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield("title", "Laundry App") - {{ config("app.name") }}</title>
    <link rel="icon" href="{{ asset("images/logo.png") }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @stack("styles")
    <style>
        :root { --font-sans: "Inter", system-ui, -apple-system, sans-serif; }
        [data-bs-theme="dark"] {
            --navy: #3949ab;
            --sidebar-bg: #1a1a2e;
            --sidebar-hover: rgba(255,255,255,0.08);
            --body-bg: #121212;
            --card-bg: #1e1e1e;
            --card-border: #2d2d2d;
            --text-primary: #e0e0e0;
            --text-muted: #9e9e9e;
            --heading-color: #e0e0e0;
            --topbar-bg: #1a1a2e;
            --topbar-border: #2d2d2d;
            --table-header-bg: #252525;
            --table-border: #2d2d2d;
            --input-bg: #2d2d2d;
            --input-border: #404040;
            --input-color: #e0e0e0;
        }
        [data-bs-theme="dark"] body { background-color: var(--body-bg); color: var(--text-primary); }
        [data-bs-theme="dark"] .topbar { background: var(--topbar-bg); border-color: var(--topbar-border); }
        [data-bs-theme="dark"] .topbar h5 { color: #90caf9; }
        [data-bs-theme="dark"] .content-wrapper { color: var(--text-primary); }
        [data-bs-theme="dark"] .stat-card { background: var(--card-bg); border-color: var(--card-border); }
        [data-bs-theme="dark"] .stat-card .value { color: #90caf9; }
        [data-bs-theme="dark"] .stat-card .label { color: var(--text-muted); }
        [data-bs-theme="dark"] .page-title { color: #90caf9; }
        [data-bs-theme="dark"] .card { background: var(--card-bg); border-color: var(--card-border); }
        [data-bs-theme="dark"] .card-header { background: var(--card-bg); border-color: var(--card-border); color: var(--text-primary); }
        [data-bs-theme="dark"] .table { color: var(--text-primary); }
        [data-bs-theme="dark"] .table th { background: var(--table-header-bg); color: var(--text-muted); border-color: var(--table-border); }
        [data-bs-theme="dark"] .table td { border-color: var(--table-border); }
        [data-bs-theme="dark"] .table-hover tbody tr:hover { background: rgba(255,255,255,0.03); }
        [data-bs-theme="dark"] .table-active { background: rgba(255,255,255,0.05) !important; }
        [data-bs-theme="dark"] .bg-light { background-color: #2d2d2d !important; }
        [data-bs-theme="dark"] .text-muted { color: var(--text-muted) !important; }
        [data-bs-theme="dark"] .btn-outline-secondary { color: var(--text-muted); border-color: #404040; }
        [data-bs-theme="dark"] .btn-outline-secondary:hover { background: #404040; color: #fff; }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select {
            background: var(--input-bg); border-color: var(--input-border); color: var(--input-color);
        }
        [data-bs-theme="dark"] .form-control:focus, [data-bs-theme="dark"] .form-select:focus {
            background: var(--input-bg); color: var(--input-color);
        }
        [data-bs-theme="dark"] .input-group-text {
            background: #2d2d2d; border-color: var(--input-border); color: var(--text-muted);
        }
        [data-bs-theme="dark"] .alert-success { background: #1b4332; border-color: #2d6a4f; color: #95d5b2; }
        [data-bs-theme="dark"] .alert-danger { background: #4a1a1a; border-color: #7a1a1a; color: #f5a5a5; }
        [data-bs-theme="dark"] .btn-primary { background-color: #3949ab; border-color: #3949ab; }
        [data-bs-theme="dark"] .btn-primary:hover { background-color: #5c6bc0; border-color: #5c6bc0; }
        [data-bs-theme="dark"] .btn-outline-primary { color: #7986cb; border-color: #7986cb; }
        [data-bs-theme="dark"] .btn-outline-primary:hover { background-color: #3949ab; border-color: #3949ab; color: #fff; }
        [data-bs-theme="dark"] .pagination .page-link { background: var(--card-bg); border-color: var(--card-border); color: var(--text-primary); }
        [data-bs-theme="dark"] .pagination .page-item.active .page-link { background: #3949ab; border-color: #3949ab; }
        [data-bs-theme="dark"] .page-item.disabled .page-link { background: var(--card-bg); color: var(--text-muted); }
        [data-bs-theme="dark"] .border-bottom { border-color: var(--card-border) !important; }
        [data-bs-theme="dark"] hr { border-color: var(--card-border); }
        [data-bs-theme="dark"] .modal-content { background: var(--card-bg); color: var(--text-primary); border-color: var(--card-border); }
        [data-bs-theme="dark"] .modal-header { border-color: var(--card-border); }
        body { font-family: var(--font-sans); background-color: #f5f7fa; }
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0; width: 250px;
            background: linear-gradient(180deg, #1a237e 0%, #283593 100%);
            padding-top: 1rem; overflow-y: auto; z-index: 1000;
        }
        .sidebar-brand {
            padding: 1rem 1.25rem; color: #fff; font-weight: 700; font-size: 1.25rem;
            display: flex; align-items: center; gap: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 0.5rem;
        }
        .sidebar-brand i { font-size: 1.5rem; color: #90caf9; }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.75); padding: 0.625rem 1.25rem;
            display: flex; align-items: center; gap: 0.75rem;
            font-size: 0.9rem; transition: all 0.2s;
        }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,0.15); border-right: 3px solid #64b5f6; }
        .sidebar .nav-link i { font-size: 1.1rem; width: 1.25rem; text-align: center; }
        .sidebar .nav-section {
            color: rgba(255,255,255,0.4); font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.08em; padding: 1rem 1.25rem 0.35rem; font-weight: 600;
        }
        .main-content { margin-left: 250px; min-height: 100vh; }
        .topbar {
            background: #fff; border-bottom: 1px solid #e9ecef;
            padding: 0.75rem 1.5rem; display: flex; align-items: center;
            justify-content: space-between; position: sticky; top: 0; z-index: 999;
        }
        .topbar h5 { margin: 0; font-weight: 600; color: #1a237e; }
        .content-wrapper { padding: 1.5rem; }
        .stat-card {
            background: #fff; border-radius: 12px; padding: 1.25rem;
            border: 1px solid #e9ecef; transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card .icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-card .value { font-size: 1.75rem; font-weight: 700; color: #1a237e; }
        .stat-card .label { font-size: 0.85rem; color: #6c757d; }
        .page-title { font-weight: 600; color: #1a237e; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .table th { font-weight: 600; font-size: 0.85rem; color: #495057; border-top: none; background: #f8f9fa; }
        .btn-primary { background-color: #1a237e; border-color: #1a237e; }
        .btn-primary:hover { background-color: #283593; border-color: #283593; }
        .btn-outline-primary { color: #1a237e; border-color: #1a237e; }
        .btn-outline-primary:hover { background-color: #1a237e; border-color: #1a237e; }
        .badge-status { font-size: 0.8rem; padding: 0.35em 0.65em; }
        .nav-link .chev { transition: transform .2s; font-size: .7rem; }
        .nav-link.collapsed .chev { transform: rotate(-90deg); }
        .card { border-radius: 12px; border: 1px solid #e9ecef; }
        .card-header { background: #fff; border-bottom: 1px solid #e9ecef; font-weight: 600; padding: 1rem 1.25rem; }
        .table > :not(caption) > * > * { vertical-align: middle; }
        .footer-note { text-align: center; padding: 1.5rem; color: #adb5bd; font-size: 0.85rem; }
        .pagination { flex-wrap: wrap; }
        .sidebar-close { position: absolute; top: 0.9rem; right: 0.75rem; z-index: 1060; filter: invert(1); }
        .sidebar-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,.5);
            z-index: 1040; opacity: 0; pointer-events: none; transition: opacity .3s;
        }
        body.sidebar-open .sidebar-backdrop { opacity: 1; pointer-events: auto; }
        @media (max-width: 767.98px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; z-index: 1050; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
        @media (min-width: 768px) {
            .sidebar-close, .sidebar-backdrop { display: none; }
        }
    </style>
</head>
<body>
    <nav class="sidebar" id="sidebar">
        <button type="button" class="btn-close sidebar-close d-md-none" id="sidebarClose" aria-label="{{ __('app.close') }}"></button>
        <div class="sidebar-brand d-flex align-items-center gap-2" style="padding:0.75rem 1rem;">
            <img src="{{ asset('images/logo.png') }}" alt="Logo" style="height:36px;width:auto;border-radius:6px;background:#fff;padding:2px;object-fit:contain;">
            <div style="line-height:1.2;">
                <div style="font-weight:800;font-size:0.95rem;color:#fff;">WS Clean &amp; Laundry</div>
                <div style="font-size:0.7rem;color:rgba(255,255,255,0.65);font-weight:500;">Serviços, Lda</div>
            </div>
        </div>
                <div class="nav-section">{{ __('app.main_menu') }}</div>
        <a href="{{ route("dashboard") }}" class="nav-link {{ request()->routeIs("dashboard") ? "active" : "" }}">
            <i class="bi bi-speedometer2"></i> {{ __('app.dashboard') }}
        </a>
        
        @if(auth()->user()->isAdmin() || auth()->user()->isCashier() || auth()->user()->isOwner())
        <a href="{{ route("orders.index") }}" class="nav-link {{ request()->routeIs("orders.*") ? "active" : "" }}">
            <i class="bi bi-receipt"></i> {{ __('app.transactions') }}
        </a>
        <a href="{{ route("customers.index") }}" class="nav-link {{ request()->routeIs("customers.*") ? "active" : "" }}">
            <i class="bi bi-people"></i> {{ __('app.customers') }}
        </a>
        @endif

        @if(auth()->user()->isAdmin())
        <a href="{{ route("services.index") }}" class="nav-link {{ request()->routeIs("services.*") ? "active" : "" }}">
            <i class="bi bi-gear"></i> {{ __('app.services') }}
        </a>
        @endif

        @if(auth()->user()->isAdmin() || auth()->user()->isOwner())
        @php($reportsActive = request()->routeIs("reports") || request()->routeIs("reports.profit-loss") || request()->routeIs("reports.payment-methods"))
        <a class="nav-link d-flex justify-content-between align-items-center {{ $reportsActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseReports" role="button" aria-expanded="{{ $reportsActive ? "true" : "false" }}" aria-controls="collapseReports">
            <span><i class="bi bi-bar-chart"></i> {{ __('app.reports') }}</span>
            <i class="bi bi-chevron-down chev"></i>
        </a>
        <div class="collapse {{ $reportsActive ? "show" : "" }}" id="collapseReports">
            <a href="{{ route("reports") }}" class="nav-link ms-3 {{ request()->routeIs("reports") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-clipboard-data"></i> {{ __('app.reports') }}
            </a>
            <a href="{{ route("reports.profit-loss") }}" class="nav-link ms-3 {{ request()->routeIs("reports.profit-loss") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-calculator"></i> {{ __('app.profit_loss') }}
            </a>
            <a href="{{ route("reports.payment-methods") }}" class="nav-link ms-3 {{ request()->routeIs("reports.payment-methods") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-credit-card"></i> {{ __('app.payment_methods_report') }}
            </a>
        </div>
        @endif

        @if(auth()->user()->isAdmin())
        <a href="{{ route("packages.index") }}" class="nav-link {{ request()->routeIs("packages.*") ? "active" : "" }}">
            <i class="bi bi-box-seam"></i> {{ __('app.packages') }}
        </a>
        @endif

        @if(auth()->user()->isAdmin() || auth()->user()->isOwner())
        @php($expensesActive = request()->routeIs("expenses.*") || request()->routeIs("expense-categories.*"))
        <a class="nav-link d-flex justify-content-between align-items-center {{ $expensesActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseExpenses" role="button" aria-expanded="{{ $expensesActive ? "true" : "false" }}" aria-controls="collapseExpenses">
            <span><i class="bi bi-cash-stack"></i> {{ __('app.expenses') }}</span>
            <i class="bi bi-chevron-down chev"></i>
        </a>
        <div class="collapse {{ $expensesActive ? "show" : "" }}" id="collapseExpenses">
            <a href="{{ route("expenses.index") }}" class="nav-link ms-3 {{ request()->routeIs("expenses.*") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-cash-stack"></i> {{ __('app.expenses') }}
            </a>
            <a href="{{ route("expense-categories.index") }}" class="nav-link ms-3 {{ request()->routeIs("expense-categories.*") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-tags"></i> {{ __('app.expense_categories') }}
            </a>
        </div>
        @endif

        @if(auth()->user()->isAdmin() || auth()->user()->isOwner())
        @php($resourcesActive = request()->routeIs("resources.*"))
        <a class="nav-link d-flex justify-content-between align-items-center {{ $resourcesActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseResources" role="button" aria-expanded="{{ $resourcesActive ? "true" : "false" }}" aria-controls="collapseResources">
            <span><i class="bi bi-inboxes"></i> {{ __('app.resources') }}</span>
            <i class="bi bi-chevron-down chev"></i>
        </a>
        <div class="collapse {{ $resourcesActive ? "show" : "" }}" id="collapseResources">
            <a href="{{ route("resources.equipments.index") }}" class="nav-link ms-3 {{ request()->routeIs("resources.equipments.*") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-gear-wide-connected"></i> {{ __('app.equipments') }}
            </a>
            <a href="{{ route("resources.items.index", ["kind" => "product"]) }}" class="nav-link ms-3 {{ request()->routeIs("resources.items.*") && !in_array(request("kind", "product"), ["material", "part"]) ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-droplet"></i> {{ __('app.products') }}
            </a>
            <a href="{{ route("resources.items.index", ["kind" => "material"]) }}" class="nav-link ms-3 {{ request()->routeIs("resources.items.*") && request("kind") == "material" ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-bag"></i> {{ __('app.materials') }}
            </a>
            <a href="{{ route("resources.maintenance.index") }}" class="nav-link ms-3 {{ request()->routeIs("resources.maintenance.*") || (request()->routeIs("resources.items.*") && request("kind") == "part") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-tools"></i> {{ __('app.maintenance') }}
            </a>
        </div>
        @endif

        @if(auth()->user()->isAdmin() || auth()->user()->isOwner())
        @php($contabilidadeActive = request()->routeIs("accounting.*"))
        <a class="nav-link d-flex justify-content-between align-items-center {{ $contabilidadeActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseAccounting" role="button" aria-expanded="{{ $contabilidadeActive ? "true" : "false" }}" aria-controls="collapseAccounting">
            <span><i class="bi bi-journal-text"></i> {{ __('app.accounting') }}</span>
            <i class="bi bi-chevron-down chev"></i>
        </a>
        <div class="collapse {{ $contabilidadeActive ? "show" : "" }}" id="collapseAccounting">
            <a href="{{ route("accounting.accounts.index") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.accounts.*") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-diagram-3"></i> {{ __('app.chart_of_accounts') }}
            </a>
            <a href="{{ route("accounting.journal-entries.index") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.journal-entries.*") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-journal-plus"></i> {{ __('app.journal') }}
            </a>
            <a href="{{ route("accounting.balancete") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.balancete") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-file-earmark-text"></i> {{ __('app.balancete') }}
            </a>
            <a href="{{ route("accounting.balanco") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.balanco") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-clipboard-data"></i> {{ __('app.balanco') }}
            </a>
            <a href="{{ route("accounting.demonstracao-resultados") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.demonstracao-resultados") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-graph-up"></i> {{ __('app.demonstracao_resultados') }}
            </a>
            <a href="{{ route("accounting.iva-acerto") }}" class="nav-link ms-3 {{ request()->routeIs("accounting.iva-acerto*") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-percent"></i> {{ __('app.iva_settlement') }}
            </a>
        </div>
        @endif

        <div class="nav-section mt-3">{{ __('app.hr_section') }}</div>
        @if(auth()->user()->isAdmin() || auth()->user()->isOwner())
        <a href="{{ route("employees.index") }}" class="nav-link {{ request()->routeIs("employees.*") ? "active" : "" }}">
            <i class="bi bi-person-badge"></i> {{ __('app.employees') }}
        </a>
        @php($salariesActive = request()->routeIs("salaries.*"))
        <a class="nav-link d-flex justify-content-between align-items-center {{ $salariesActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseSalaries" role="button" aria-expanded="{{ $salariesActive ? "true" : "false" }}" aria-controls="collapseSalaries">
            <span><i class="bi bi-cash-coin"></i> {{ __('app.salaries') }}</span>
            <i class="bi bi-chevron-down chev"></i>
        </a>
        <div class="collapse {{ $salariesActive ? "show" : "" }}" id="collapseSalaries">
            <a href="{{ route("salaries.index") }}" class="nav-link ms-3 {{ request()->routeIs("salaries.summary") ? "" : "active" }}" style="font-size:0.85rem">
                <i class="bi bi-cash-coin"></i> {{ __('app.salaries') }}
            </a>
            <a href="{{ route("salaries.summary") }}" class="nav-link ms-3 {{ request()->routeIs("salaries.summary") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-pie-chart"></i> {{ __('app.salary_summary') }}
            </a>
        </div>
        @php($vacationsActive = request()->routeIs("vacations.*"))
        <a class="nav-link d-flex justify-content-between align-items-center {{ $vacationsActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseVacations" role="button" aria-expanded="{{ $vacationsActive ? "true" : "false" }}" aria-controls="collapseVacations">
            <span><i class="bi bi-umbrella"></i> {{ __('app.vacations') }}</span>
            <i class="bi bi-chevron-down chev"></i>
        </a>
        <div class="collapse {{ $vacationsActive ? "show" : "" }}" id="collapseVacations">
            <a href="{{ route("vacations.index") }}" class="nav-link ms-3 {{ request()->routeIs("vacations.plan") ? "" : "active" }}" style="font-size:0.85rem">
                <i class="bi bi-umbrella"></i> {{ __('app.vacations') }}
            </a>
            <a href="{{ route("vacations.plan") }}" class="nav-link ms-3 {{ request()->routeIs("vacations.plan") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-calendar3"></i> {{ __('app.vacation_plan') }}
            </a>
        </div>
        <a href="{{ route("leaves.index") }}" class="nav-link {{ request()->routeIs("leaves.*") ? "active" : "" }}">
            <i class="bi bi-slash-circle"></i> {{ __('app.dispensations') }}
        </a>
        @php($attendanceActive = request()->routeIs("attendance.*") || request()->routeIs("justifications.*"))
        <a class="nav-link d-flex justify-content-between align-items-center {{ $attendanceActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseAttendance" role="button" aria-expanded="{{ $attendanceActive ? "true" : "false" }}" aria-controls="collapseAttendance">
            <span><i class="bi bi-calendar-check"></i> {{ __('app.attendance') }}</span>
            <i class="bi bi-chevron-down chev"></i>
        </a>
        <div class="collapse {{ $attendanceActive ? "show" : "" }}" id="collapseAttendance">
            <a href="{{ route("attendance.index") }}" class="nav-link ms-3 {{ request()->routeIs("attendance.index") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-calendar-check"></i> {{ __('app.daily_attendance') }}
            </a>
            <a href="{{ route("attendance.summary") }}" class="nav-link ms-3 {{ request()->routeIs("attendance.summary") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-clipboard-data"></i> {{ __('app.attendance_summary') }}
            </a>
            <a href="{{ route("attendance.daily") }}" class="nav-link ms-3 {{ request()->routeIs("attendance.daily") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-grid-3x3-gap"></i> {{ __('app.attendance_daily') }}
            </a>
            <a href="{{ route("justifications.index") }}" class="nav-link ms-3 {{ request()->routeIs("justifications.*") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-file-earmark-text"></i> {{ __('app.absence_justifications') }}
            </a>
        </div>
        @endif
        @if(auth()->user()->isCashier())
        <a href="{{ route("hr.payslips") }}" class="nav-link {{ request()->routeIs("hr.payslips") ? "active" : "" }}">
            <i class="bi bi-cash-coin"></i> {{ __('app.my_payslips') }}
        </a>
        <a href="{{ route("hr.attendance") }}" class="nav-link {{ request()->routeIs("hr.attendance") ? "active" : "" }}">
            <i class="bi bi-calendar-check"></i> {{ __('app.my_attendance') }}
        </a>
        @endif

        <div class="nav-section mt-3">{{ __('app.front_page') }}</div>
        @if(auth()->user()->hasRole(["admin", "owner"]))
        <a href="{{ route("users.index") }}" class="nav-link {{ request()->routeIs("users.*") ? "active" : "" }}">
            <i class="bi bi-person-gear"></i> {{ __('app.users') }}
        </a>
        @php($settingsActive = request()->routeIs("admin.landing-settings.*") || request()->routeIs("admin.business-settings.*"))
        <a class="nav-link d-flex justify-content-between align-items-center {{ $settingsActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseSettings" role="button" aria-expanded="{{ $settingsActive ? "true" : "false" }}" aria-controls="collapseSettings">
            <span><i class="bi bi-sliders"></i> {{ __('app.settings') }}</span>
            <i class="bi bi-chevron-down chev"></i>
        </a>
        <div class="collapse {{ $settingsActive ? "show" : "" }}" id="collapseSettings">
            <a href="{{ route("admin.landing-settings.index") }}" class="nav-link ms-3 {{ request()->routeIs("admin.landing-settings.*") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-house"></i> {{ __('app.landing_page_settings') }}
            </a>
            <a href="{{ route("admin.business-settings.index") }}" class="nav-link ms-3 {{ request()->routeIs("admin.business-settings.*") ? "active" : "" }}" style="font-size:0.85rem">
                <i class="bi bi-percent"></i> {{ __('app.business_settings') }}
            </a>
        </div>
        <a href="{{ route("admin.testimonials.index") }}" class="nav-link {{ request()->routeIs("admin.testimonials.*") ? "active" : "" }}">
            <i class="bi bi-star"></i> {{ __('app.testimonials') }}
        </a>
        <a href="{{ route("admin.galleries.index") }}" class="nav-link {{ request()->routeIs("admin.galleries.*") ? "active" : "" }}">
            <i class="bi bi-images"></i> {{ __('app.gallery') }}
        </a>
        @endif
        <div class="nav-section mt-3">{{ __('app.account') }}</div>
        <a href="{{ route("profile.edit") }}" class="nav-link">
            <i class="bi bi-person"></i> {{ __('app.profile') }}
        </a>
        @if(auth()->user()->hasRole(["admin", "owner"]))
        <a href="{{ route("activity-logs.index") }}" class="nav-link {{ request()->routeIs("activity-logs.*") ? "active" : "" }}">
            <i class="bi bi-clock-history"></i> {{ __('app.activity_log') }}
        </a>
        @endif
        <form method="POST" action="{{ route("logout") }}" class="d-inline">
            @csrf
            <button type="submit" class="nav-link w-100 text-start" style="background:none;border:none;cursor:pointer">
                <i class="bi bi-box-arrow-right"></i> {{ __('app.logout') }}
            </button>
        </form>
        <div class="mt-auto p-3" style="border-top:1px solid rgba(255,255,255,0.1)">
            <small style="color:rgba(255,255,255,0.4);font-size:0.75rem">&copy; {{ date("Y") }} WS Clean & Laundry Serviços, Lda</small>
        </div>
    </nav>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-content">
        <div class="topbar">
            <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle" aria-controls="sidebar" aria-expanded="false" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h5>@yield("page_title", "Dashboard")</h5>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-translate"></i>
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item {{ app()->getLocale() == 'pt' ? 'active' : '' }}" href="{{ route('language.switch', 'pt') }}">🇵🇹 Português</a></li>
                        <li><a class="dropdown-item {{ app()->getLocale() == 'en' ? 'active' : '' }}" href="{{ route('language.switch', 'en') }}">🇬🇧 English</a></li>
                    </ul>
                </div>
                <button id="darkModeToggle" class="btn btn-sm btn-outline-secondary" title="Toggle Dark Mode"
                    onclick="toggleDarkMode()">
                    <i id="darkModeIcon" class="bi bi-moon-stars"></i>
                </button>
                <span class="text-muted small">{{ auth()->user()->name ?? "Admin" }}</span>
                <span class="badge bg-{{ optional(auth()->user())->isCashier() ? "info" : (optional(auth()->user())->isOwner() ? "primary" : "danger") }} me-2">{{ __('app.role_' . (auth()->user()->role ?? "admin")) }}</span>
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-weight:600;font-size:0.9rem">
                    {{ strtoupper(substr(auth()->user()->name ?? "A", 0, 1)) }}
                </div>
            </div>
        </div>
        <div class="content-wrapper">
            @if(session("success"))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-1"></i> {{ session("success") }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session("error"))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-1"></i> {{ session("error") }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @yield("content")
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            var theme = localStorage.getItem("theme") || "light";
            document.documentElement.setAttribute("data-bs-theme", theme);
            var icon = document.getElementById("darkModeIcon");
            if (icon) icon.className = theme === "dark" ? "bi bi-sun" : "bi bi-moon-stars";
        })();
        function toggleDarkMode() {
            var html = document.documentElement;
            var current = html.getAttribute("data-bs-theme");
            var next = current === "dark" ? "light" : "dark";
            html.setAttribute("data-bs-theme", next);
            localStorage.setItem("theme", next);
            var icon = document.getElementById("darkModeIcon");
            if (icon) icon.className = next === "dark" ? "bi bi-sun" : "bi bi-moon-stars";
        }
    </script>

    <script>
        function toggleSidebar(force) {
            var sb = document.getElementById("sidebar");
            var open = force !== undefined ? force : !sb.classList.contains("show");
            sb.classList.toggle("show", open);
            document.body.classList.toggle("sidebar-open", open);
            document.getElementById("sidebarToggle").setAttribute("aria-expanded", String(open));
        }
        document.addEventListener("click", function(e) {
            if (e.target.closest("#sidebarClose") || e.target.id === "sidebarBackdrop") toggleSidebar(false);
            else if (e.target.closest("#sidebar .nav-link")) toggleSidebar(false);
        });
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") toggleSidebar(false);
        });
    </script>

    <script>
        // Prevent double form submission
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll("form").forEach(function(form) {
                form.addEventListener("submit", function(e) {
                    var btn = form.querySelector("button[type=submit]");
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> {{ __('app.loading') }}';
                    }
                });
            });
        });
    </script>
    @stack("scripts")
</body>
</html>