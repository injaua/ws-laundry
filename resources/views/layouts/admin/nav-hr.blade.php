{{-- Sidebar · Human resources. --}}
<div class="nav-section mt-3">{{ __('app.hr_section') }}</div>
@if(auth()->user()->isAdmin() || auth()->user()->isOwner())
<a href="{{ route("employees.index") }}" class="nav-link {{ request()->routeIs("employees.*") ? "active" : "" }}">
    <i class="bi bi-person-badge"></i> {{ __('app.employees') }}
</a>
@endif
@if(auth()->user()->seesFinance())
@php($salariesActive = request()->routeIs("salaries.*"))
<a class="nav-link d-flex justify-content-between align-items-center {{ $salariesActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseSalaries" role="button" aria-expanded="{{ $salariesActive ? "true" : "false" }}" aria-controls="collapseSalaries">
    <span><i class="bi bi-cash-coin"></i> {{ __('app.salaries') }}</span>
    <i class="bi bi-chevron-down chev"></i>
</a>
<div class="collapse {{ $salariesActive ? "show" : "" }}" id="collapseSalaries">
    <a href="{{ route("salaries.index") }}" class="nav-link ms-3 {{ request()->routeIs("salaries.summary") ? "" : "active" }} nav-sub">
        <i class="bi bi-cash-coin"></i> {{ __('app.salaries') }}
    </a>
    <a href="{{ route("salaries.summary") }}" class="nav-link ms-3 {{ request()->routeIs("salaries.summary") ? "active" : "" }} nav-sub">
        <i class="bi bi-pie-chart"></i> {{ __('app.salary_summary') }}
    </a>
</div>
@endif
@if(auth()->user()->isManager())
@php($vacationsActive = request()->routeIs("vacations.*"))
<a class="nav-link d-flex justify-content-between align-items-center {{ $vacationsActive ? "" : "collapsed" }}" data-bs-toggle="collapse" href="#collapseVacations" role="button" aria-expanded="{{ $vacationsActive ? "true" : "false" }}" aria-controls="collapseVacations">
    <span><i class="bi bi-umbrella"></i> {{ __('app.vacations') }}</span>
    <i class="bi bi-chevron-down chev"></i>
</a>
<div class="collapse {{ $vacationsActive ? "show" : "" }}" id="collapseVacations">
    <a href="{{ route("vacations.index") }}" class="nav-link ms-3 {{ request()->routeIs("vacations.plan") ? "" : "active" }} nav-sub">
        <i class="bi bi-umbrella"></i> {{ __('app.vacations') }}
    </a>
    <a href="{{ route("vacations.plan") }}" class="nav-link ms-3 {{ request()->routeIs("vacations.plan") ? "active" : "" }} nav-sub">
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
    <a href="{{ route("attendance.index") }}" class="nav-link ms-3 {{ request()->routeIs("attendance.index") ? "active" : "" }} nav-sub">
        <i class="bi bi-calendar-check"></i> {{ __('app.daily_attendance') }}
    </a>
    <a href="{{ route("attendance.summary") }}" class="nav-link ms-3 {{ request()->routeIs("attendance.summary") ? "active" : "" }} nav-sub">
        <i class="bi bi-clipboard-data"></i> {{ __('app.attendance_summary') }}
    </a>
    <a href="{{ route("attendance.daily") }}" class="nav-link ms-3 {{ request()->routeIs("attendance.daily") ? "active" : "" }} nav-sub">
        <i class="bi bi-grid-3x3-gap"></i> {{ __('app.attendance_daily') }}
    </a>
    <a href="{{ route("justifications.index") }}" class="nav-link ms-3 {{ request()->routeIs("justifications.*") ? "active" : "" }} nav-sub">
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
