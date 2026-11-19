@extends("layouts.admin")
@section("title", $employee->name)
@section("page_title", __('app.employee_profile'))

@section("content")
@php
    $statusColors = ["probation" => "warning", "active" => "success", "suspended" => "secondary", "terminated" => "danger"];
    $payColors = ["pending" => "secondary", "paid" => "success"];
    $reviewColors = ["pending" => "secondary", "approved" => "success", "rejected" => "danger"];
@endphp
<div class="page-title">
    <span>
        {{ $employee->name }}
        <span class="badge badge-status bg-{{ $statusColors[$employee->status] }}">{{ $employee->status_label }}</span>
    </span>
    <div class="d-flex gap-2">
        <a href="{{ route("employees.edit", $employee) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> {{ __('app.edit') }}</a>
        <a href="{{ route("employees.index") }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> {{ __('app.back') }}</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-person-badge me-1"></i> {{ __('app.employee') }}</div>
            <div class="card-body">
                <div class="row g-2 small">
                    <div class="col-md-4"><span class="text-muted">{{ __('app.position') }}:</span><br><strong>{{ $employee->position }}</strong></div>
                    <div class="col-md-4"><span class="text-muted">{{ __('app.bi_number') }}:</span><br><strong>{{ $employee->bi_number ?? "—" }}</strong></div>
                    <div class="col-md-4"><span class="text-muted">{{ __('app.phone') }}:</span><br><strong>{{ $employee->phone ?? "—" }}</strong></div>
                    <div class="col-md-4"><span class="text-muted">{{ __('app.hire_date') }}:</span><br><strong>{{ $employee->hire_date->format(date_fmt()) }}</strong></div>
                    <div class="col-md-4"><span class="text-muted">{{ __('app.probation_ends_at') }}:</span><br><strong>{{ $employee->probation_ends_at?->format(date_fmt()) ?? "—" }}</strong></div>
                    <div class="col-md-4"><span class="text-muted">{{ __('app.contract_type') }}:</span><br><strong>{{ __('app.contract_' . $employee->contract_type) }}</strong></div>
                    <div class="col-md-4"><span class="text-muted">{{ __('app.base_salary') }}:</span><br><strong>{{ money($employee->base_salary) }}</strong></div>
                    <div class="col-md-4"><span class="text-muted">{{ __('app.link_user') }}:</span><br>
                        <strong>
                            @if($employee->user)
                                {{ $employee->user->name }} ({{ __('app.role_' . $employee->user->role) }})
                            @else
                                {{ __('app.link_user_none') }}
                            @endif
                        </strong>
                    </div>
                    @if($employee->status === "terminated")
                        <div class="col-md-4"><span class="text-muted">{{ __('app.termination_date') }}:</span><br><strong>{{ $employee->termination_date?->format(date_fmt()) ?? "—" }}</strong></div>
                        <div class="col-12"><span class="text-muted">{{ __('app.termination_reason') }}:</span><br>{{ $employee->termination_reason }}</div>
                    @endif
                    @if($employee->notes)
                        <div class="col-12"><span class="text-muted">{{ __('app.notes') }}:</span><br>{{ $employee->notes }}</div>
                    @endif
                </div>

                <hr>
                <div class="d-flex flex-wrap gap-2">
                    @if($employee->status === "probation")
                        <form action="{{ route("employees.status", $employee) }}" method="POST">
                            @csrf @method("PATCH")
                            <input type="hidden" name="status" value="active">
                            <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> {{ __('app.confirm_probation_end') }}</button>
                        </form>
                    @elseif($employee->status === "active")
                        <form action="{{ route("employees.status", $employee) }}" method="POST" data-confirm="{{ __('app.terminate_confirm') }}">
                            @csrf @method("PATCH")
                            <input type="hidden" name="status" value="suspended">
                            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-pause-circle"></i> {{ __('app.suspend') }}</button>
                        </form>
                        <a href="{{ route("employees.terminate", $employee) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-person-x"></i> {{ __('app.terminate') }}</a>
                    @elseif($employee->status === "suspended")
                        <form action="{{ route("employees.status", $employee) }}" method="POST">
                            @csrf @method("PATCH")
                            <input type="hidden" name="status" value="active">
                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-play-circle"></i> {{ __('app.reactivate') }}</button>
                        </form>
                        <a href="{{ route("employees.terminate", $employee) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-person-x"></i> {{ __('app.terminate') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-check me-1"></i> {{ __('app.attendance') }} — {{ now()->format("m/Y") }}</div>
            <div class="card-body">
                <div class="d-flex justify-content-around text-center">
                    <div>
                        <div class="fs-2 fw-bold text-success">{{ $summary["present"] }}</div>
                        <div class="small text-muted">{{ __('app.days_present') }}</div>
                    </div>
                    <div>
                        <div class="fs-2 fw-bold text-danger">{{ $summary["absent"] }}</div>
                        <div class="small text-muted">{{ __('app.days_absent') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cash-coin me-1"></i> {{ __('app.payslips') }}</span>
        <a href="{{ route("salaries.create") }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> {{ __('app.new_payslip') }}</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.period_month') }}/{{ __('app.period_year') }}</th>
                    <th>{{ __('app.base_salary') }}</th>
                    <th>{{ __('app.bonus') }}</th>
                    <th>{{ __('app.deduction') }}</th>
                    <th>{{ __('app.absence_deduction') }}</th>
                    <th>{{ __('app.net_salary') }}</th>
                    <th>{{ __('app.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employee->salaries as $salary)
                <tr>
                    <td>{{ $salary->periodLabel() }}</td>
                    <td>{{ num($salary->base_salary) }}</td>
                    <td class="text-success">{{ num($salary->bonus) }}</td>
                    <td class="text-danger">{{ num($salary->deduction) }}</td>
                    <td class="text-danger">{{ num($salary->absence_deduction) }}</td>
                    <td class="fw-semibold">{{ money($salary->net_salary) }}</td>
                    <td><span class="badge bg-{{ $payColors[$salary->status] }}">{{ __('app.' . $salary->status) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-3 text-muted">{{ __('app.no_salaries') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-umbrella me-1"></i> {{ __('app.vacations') }}</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.start_date') }}</th>
                            <th>{{ __('app.end_date') }}</th>
                            <th>{{ __('app.working_days') }}</th>
                            <th>{{ __('app.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->vacations as $vacation)
                        <tr>
                            <td>{{ $vacation->start_date->format(date_fmt()) }}</td>
                            <td>{{ $vacation->end_date->format(date_fmt()) }}</td>
                            <td>{{ $vacation->days }}</td>
                            <td><span class="badge bg-{{ $reviewColors[$vacation->status] }}">{{ __('app.' . $vacation->status) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center py-3 text-muted">{{ __('app.no_vacations') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-slash-circle me-1"></i> {{ __('app.dispensations') }}</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.leave_date') }}</th>
                            <th>{{ __('app.hours') }}</th>
                            <th>{{ __('app.reason') }}</th>
                            <th>{{ __('app.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->leaves as $leave)
                        <tr>
                            <td>{{ $leave->leave_date->format(date_fmt()) }}</td>
                            <td>{{ $leave->hours }}</td>
                            <td class="small">{{ Str::limit($leave->reason, 40) }}</td>
                            <td><span class="badge bg-{{ $reviewColors[$leave->status] }}">{{ __('app.' . $leave->status) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center py-3 text-muted">{{ __('app.no_dispensations') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
