@extends("layouts.admin")
@section("title", __('app.my_attendance'))
@section("page_title", __('app.my_attendance'))

@section("content")
@if(!$employee)
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-person-x fs-1 d-block mb-2"></i>
        {{ __('app.no_employee_record') }}
    </div>
</div>
@else
@php
    $statusColors = ["present" => "success", "absent" => "danger", "late" => "warning", "excused" => "info", "vacation" => "primary"];
@endphp
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="value">{{ $summary["present"] }}</div>
            <div class="label">{{ __('app.days_present') }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="value text-danger">{{ $summary["absent"] }}</div>
            <div class="label">{{ __('app.days_absent') }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <form action="{{ route("hr.attendance") }}" method="GET" class="input-group">
            <input type="month" name="month" class="form-control" value="{{ $month }}">
            <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-calendar-check me-1"></i> {{ $employee->name }} — {{ __('app.my_attendance') }} {{ \Carbon\Carbon::parse($month . "-01")->format("m/Y") }}</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.date') }}</th>
                    <th>{{ __('app.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $att)
                <tr>
                    <td>{{ $att->attendance_date->format(date_fmt()) }}</td>
                    <td>
                        <span class="badge bg-{{ $statusColors[$att->status] }}">
                            {{ __('app.' . ($att->status === "vacation" ? "on_vacation" : $att->status)) }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="2" class="text-center py-4 text-muted">{{ __('app.no_attendance') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
