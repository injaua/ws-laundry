{{-- Topbar · Signed-in user: name and role, with the account pages and logout. --}}
@php($user = auth()->user())
@php($roleColor = match ($user->role) { "cashier" => "info", "owner" => "primary", "accountant" => "success", "courier" => "warning", default => "danger" })
<div class="dropdown">
    <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2 user-menu-btn dropdown-toggle"
            data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('app.account') }}: {{ $user->name }}">
        <i class="bi bi-person-circle fs-5 lh-1"></i>
        <span class="d-none d-sm-flex flex-column align-items-start lh-sm text-start">
            <span class="fw-semibold text-truncate user-menu-name">{{ $user->name }}</span>
            <span class="text-muted user-menu-role">{{ __('app.role_' . $user->role) }}</span>
        </span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm user-menu">
        <li class="px-3 py-2">
            <div class="fw-semibold text-truncate">{{ $user->name }}</div>
            <div class="small text-muted text-truncate">{{ $user->email }}</div>
            <span class="badge bg-{{ $roleColor }} mt-1">{{ __('app.role_' . $user->role) }}</span>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item {{ request()->routeIs("profile.edit") ? "active" : "" }}" href="{{ route("profile.edit") }}">
                <i class="bi bi-person me-2"></i>{{ __('app.profile') }}
            </a>
        </li>
        <li>
            <a class="dropdown-item {{ request()->routeIs("profile.security") ? "active" : "" }}" href="{{ route("profile.security") }}">
                <i class="bi bi-shield-check me-2"></i>{{ __('app.manage_security') }}
            </a>
        </li>
        @if($user->can("hr-self-service"))
            <li>
                <a class="dropdown-item {{ request()->routeIs("hr.payslips") ? "active" : "" }}" href="{{ route("hr.payslips") }}">
                    <i class="bi bi-cash-coin me-2"></i>{{ __('app.my_payslips') }}
                </a>
            </li>
            <li>
                <a class="dropdown-item {{ request()->routeIs("hr.attendance") ? "active" : "" }}" href="{{ route("hr.attendance") }}">
                    <i class="bi bi-calendar-check me-2"></i>{{ __('app.my_attendance') }}
                </a>
            </li>
        @endif
        @if($user->can("manage-store"))
            <li>
                <a class="dropdown-item {{ request()->routeIs("activity-logs.*") ? "active" : "" }}" href="{{ route("activity-logs.index") }}">
                    <i class="bi bi-clock-history me-2"></i>{{ __('app.activity_log') }}
                </a>
            </li>
        @endif
        <li>
            <a class="dropdown-item {{ request()->routeIs("help.*") ? "active" : "" }}" href="{{ route("help.index") }}">
                <i class="bi bi-book me-2"></i>{{ __('app.help') }}
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <form method="POST" action="{{ route("logout") }}">
                @csrf
                <button type="submit" class="dropdown-item text-danger">
                    <i class="bi bi-box-arrow-right me-2"></i>{{ __('app.logout') }}
                </button>
            </form>
        </li>
    </ul>
</div>
