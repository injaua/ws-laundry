@extends("layouts.admin")
@section("title", __('app.employees'))
@section("page_title", __('app.employees'))

@section("content")
@php
    $statusColors = ["probation" => "warning", "active" => "success", "suspended" => "secondary", "terminated" => "danger"];
@endphp
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $employees->total()]) }}</span>
    <a href="{{ route("employees.create") }}" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> {{ __('app.new_employee') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("employees.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search_employees') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.status') }}</option>
                        @foreach(\App\Models\Employee::STATUSES as $st)
                            <option value="{{ $st }}" {{ request("status") == $st ? "selected" : "" }}>{{ __('app.status_' . $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("employees.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.employee') }}</th>
                        <th>{{ __('app.position') }}</th>
                        <th>{{ __('app.contract_type') }}</th>
                        <th>{{ __('app.hire_date') }}</th>
                        <th>{{ __('app.base_salary') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.role') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-medium">
                            <a href="{{ route("employees.show", $employee) }}" class="text-decoration-none">{{ $employee->name }}</a>
                            @if($employee->bi_number)
                                <br><small class="text-muted">{{ $employee->bi_number }}</small>
                            @endif
                        </td>
                        <td>{{ $employee->position }}</td>
                        <td class="small">{{ __('app.contract_' . $employee->contract_type) }}</td>
                        <td class="small">{{ $employee->hire_date->format(date_fmt()) }}</td>
                        <td class="fw-semibold">{{ money($employee->base_salary) }}</td>
                        <td>
                            <span class="badge badge-status bg-{{ $statusColors[$employee->status] ?? "secondary" }}">
                                {{ $employee->status_label }}
                            </span>
                        </td>
                        <td class="small">
                            @if($employee->user)
                                <span class="badge bg-light text-dark">{{ __('app.role_' . $employee->user->role) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route("employees.show", $employee) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.view') }}">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route("employees.edit", $employee) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route("employees.destroy", $employee) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_employee_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="bi bi-person-badge fs-1 d-block mb-2"></i>
                            {{ __('app.no_employees') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $employees->links() }}</div>
@endsection
