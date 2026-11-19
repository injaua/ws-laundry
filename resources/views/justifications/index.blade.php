@extends("layouts.admin")
@section("title", __('app.absence_justifications'))
@section("page_title", __('app.absence_justifications'))

@section("content")
@php
    $reviewColors = ["pending" => "secondary", "approved" => "success", "rejected" => "danger"];
@endphp
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $justifications->total()]) }}</span>
    <a href="{{ route("justifications.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_justification') }}
    </a>
</div>

@if($unjustified->isNotEmpty())
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-exclamation-triangle me-1"></i> {{ __('app.unjustified_absences') }}</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <tbody>
                @foreach($unjustified as $att)
                <tr>
                    <td class="fw-medium">{{ $att->employee->name }}</td>
                    <td class="small">{{ $att->attendance_date->format(date_fmt()) }}</td>
                    <td>
                        <span class="badge bg-{{ ["present" => "success", "absent" => "danger", "late" => "warning", "excused" => "info", "vacation" => "primary"][$att->status] }}">
                            {{ __('app.' . ($att->status === "vacation" ? "on_vacation" : $att->status)) }}
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route("justifications.create", ["employee_id" => $att->employee_id, "date" => $att->attendance_date->toDateString()]) }}"
                           class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-text"></i> {{ __('app.justify') }}</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("justifications.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <select name="employee_id" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.employees') }}</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request("employee_id") == $employee->id ? "selected" : "" }}>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.status') }}</option>
                        @foreach(\App\Models\AbsenceJustification::STATUSES as $st)
                            <option value="{{ $st }}" {{ request("status") == $st ? "selected" : "" }}>{{ __('app.' . $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("justifications.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.employee') }}</th>
                        <th>{{ __('app.absence_date') }}</th>
                        <th>{{ __('app.reason') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($justifications as $justification)
                    <tr>
                        <td class="fw-medium">
                            <a href="{{ route("employees.show", $justification->employee) }}" class="text-decoration-none">{{ $justification->employee->name }}</a>
                        </td>
                        <td class="small">{{ $justification->absence_date->format(date_fmt()) }}</td>
                        <td>
                            {{ $justification->reason }}
                            @if($justification->notes)
                                <br><small class="text-muted">{{ $justification->notes }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $reviewColors[$justification->status] }}">
                                {{ __('app.' . $justification->status) }}
                            </span>
                            @if($justification->status !== "pending" && $justification->reviewer)
                                <br><small class="text-muted">{{ $justification->reviewer->name }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($justification->status === "pending")
                                <form action="{{ route("justifications.review", $justification) }}" method="POST" class="d-inline">
                                    @csrf @method("PATCH")
                                    <input type="hidden" name="decision" value="approved">
                                    <button class="btn btn-sm btn-outline-success" title="{{ __('app.approve') }}"><i class="bi bi-check2"></i></button>
                                </form>
                                <form action="{{ route("justifications.review", $justification) }}" method="POST" class="d-inline">
                                    @csrf @method("PATCH")
                                    <input type="hidden" name="decision" value="rejected">
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.reject') }}"><i class="bi bi-x"></i></button>
                                </form>
                            @endif
                            <form action="{{ route("justifications.destroy", $justification) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_justification_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-file-earmark-text fs-1 d-block mb-2"></i>
                            {{ __('app.no_justifications') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $justifications->links() }}</div>
@endsection
