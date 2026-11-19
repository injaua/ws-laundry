@extends("layouts.admin")
@section("title", __('app.dispensations'))
@section("page_title", __('app.dispensations'))

@section("content")
@php
    $reviewColors = ["pending" => "secondary", "approved" => "success", "rejected" => "danger"];
@endphp
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $leaves->total()]) }}</span>
    <a href="{{ route("leaves.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_dispensation') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("leaves.index") }}" method="GET" class="row g-2">
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
                        @foreach(\App\Models\Leave::STATUSES as $st)
                            <option value="{{ $st }}" {{ request("status") == $st ? "selected" : "" }}>{{ __('app.' . $st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("leaves.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.employee') }}</th>
                        <th>{{ __('app.leave_date') }}</th>
                        <th>{{ __('app.hours') }}</th>
                        <th>{{ __('app.reason') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaves as $leave)
                    <tr>
                        <td class="fw-medium">
                            <a href="{{ route("employees.show", $leave->employee) }}" class="text-decoration-none">{{ $leave->employee->name }}</a>
                        </td>
                        <td>{{ $leave->leave_date->format(date_fmt()) }}</td>
                        <td>{{ $leave->hours }}</td>
                        <td class="small">{{ $leave->reason }}</td>
                        <td>
                            <span class="badge bg-{{ $reviewColors[$leave->status] }}">
                                {{ __('app.' . $leave->status) }}
                            </span>
                            @if($leave->status !== "pending" && $leave->reviewer)
                                <br><small class="text-muted">{{ $leave->reviewer->name }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($leave->status === "pending")
                                <form action="{{ route("leaves.review", $leave) }}" method="POST" class="d-inline">
                                    @csrf @method("PATCH")
                                    <input type="hidden" name="decision" value="approved">
                                    <button class="btn btn-sm btn-outline-success" title="{{ __('app.approve') }}"><i class="bi bi-check2"></i></button>
                                </form>
                                <form action="{{ route("leaves.review", $leave) }}" method="POST" class="d-inline">
                                    @csrf @method("PATCH")
                                    <input type="hidden" name="decision" value="rejected">
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.reject') }}"><i class="bi bi-x"></i></button>
                                </form>
                            @endif
                            <form action="{{ route("leaves.destroy", $leave) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_dispensation_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-slash-circle fs-1 d-block mb-2"></i>
                            {{ __('app.no_dispensations') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $leaves->links() }}</div>
@endsection
