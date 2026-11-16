@extends("layouts.admin")
@section("title", __('app.vacations'))
@section("page_title", __('app.vacations'))

@section("content")
@php
    $reviewColors = ["pending" => "secondary", "approved" => "success", "rejected" => "danger"];
@endphp
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $vacations->total()]) }}</span>
    <a href="{{ route("vacations.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_vacation') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("vacations.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <select name="employee_id" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('app.employees') }}</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request("employee_id") == $employee->id ? "selected" : "" }}>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('app.status') }}</option>
                        @foreach(\App\Models\Vacation::STATUSES as $st)
                            <option value="{{ $st }}" {{ request("status") == $st ? "selected" : "" }}>{{ __('app.' . $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("vacations.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.employee') }}</th>
                        <th>{{ __('app.vacation_type') }}</th>
                        <th>{{ __('app.start_date') }}</th>
                        <th>{{ __('app.end_date') }}</th>
                        <th>{{ __('app.working_days') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vacations as $vacation)
                    <tr>
                        <td class="fw-medium">
                            <a href="{{ route("employees.show", $vacation->employee) }}" class="text-decoration-none">{{ $vacation->employee->name }}</a>
                        </td>
                        <td class="small">{{ __('app.vacation_type_' . $vacation->type) }}</td>
                        <td>{{ $vacation->start_date->format("d/m/Y") }}</td>
                        <td>{{ $vacation->end_date->format("d/m/Y") }}</td>
                        <td>{{ $vacation->days }}</td>
                        <td>
                            <span class="badge bg-{{ $reviewColors[$vacation->status] }}">
                                {{ __('app.' . $vacation->status) }}
                            </span>
                            @if($vacation->status !== "pending" && $vacation->reviewer)
                                <br><small class="text-muted">{{ $vacation->reviewer->name }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($vacation->status === "pending")
                                <form action="{{ route("vacations.review", $vacation) }}" method="POST" class="d-inline">
                                    @csrf @method("PATCH")
                                    <input type="hidden" name="decision" value="approved">
                                    <button class="btn btn-sm btn-outline-success" title="{{ __('app.approve') }}"><i class="bi bi-check2"></i></button>
                                </form>
                                <form action="{{ route("vacations.review", $vacation) }}" method="POST" class="d-inline">
                                    @csrf @method("PATCH")
                                    <input type="hidden" name="decision" value="rejected">
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.reject') }}"><i class="bi bi-x"></i></button>
                                </form>
                            @endif
                            <a href="{{ route("vacations.edit", $vacation) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route("vacations.destroy", $vacation) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('app.delete_vacation_confirm') }}')">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-umbrella fs-1 d-block mb-2"></i>
                            {{ __('app.no_vacations') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $vacations->links() }}</div>
@endsection
