@extends("layouts.admin")
@section("title", __('app.daily_attendance'))
@section("page_title", __('app.daily_attendance'))

@section("content")
@php
    $statusColors = ["present" => "success", "absent" => "danger", "late" => "warning", "excused" => "info", "vacation" => "primary"];
@endphp
<div class="page-title">
    <span>{{ \Carbon\Carbon::parse($date)->locale(app()->getLocale())->isoFormat("LL") }}</span>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom row g-2 align-items-center">
            <div class="col-md-auto">
                <form action="{{ route("attendance.index") }}" method="GET" class="input-group">
                    <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                    <input type="date" name="date" class="form-control" value="{{ $date }}">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                </form>
            </div>
        </div>

        <form action="{{ route("attendance.store") }}" method="POST">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('app.employee') }}</th>
                            <th>{{ __('app.position') }}</th>
                            <th>{{ __('app.status') }}</th>
                            <th class="minw-180px">{{ __('app.attendance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                        @php
                            $current = $registered->get($employee->id)?->status ?? ($defaults[$employee->id] ?? "");
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="fw-medium">
                                <a href="{{ route("employees.show", $employee) }}" class="text-decoration-none">{{ $employee->name }}</a>
                            </td>
                            <td class="small">{{ $employee->position }}</td>
                            <td>
                                <span class="badge badge-status bg-{{ ["probation" => "warning", "active" => "success", "suspended" => "secondary"][$employee->status] }}">
                                    {{ $employee->status_label }}
                                </span>
                                @if($employee->user)
                                    <span class="badge bg-light text-dark">{{ __('app.role_' . $employee->user->role) }}</span>
                                @endif
                            </td>
                            <td>
                                <select name="attendance[{{ $employee->id }}]" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @foreach(\App\Models\Attendance::STATUSES as $st)
                                        <option value="{{ $st }}" {{ $current === $st ? "selected" : "" }}>{{ __('app.' . ($st === "vacation" ? "on_vacation" : $st)) }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">{{ __('app.no_employees') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($employees->count())
            <div class="p-3 border-top d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.register_attendance') }}</button>
            </div>
            @endif
        </form>
    </div>
</div>

@if($registered->isNotEmpty())
<div class="card mt-3">
    <div class="card-header"><i class="bi bi-clock-history me-1"></i> {{ __('app.register_attendance') }} — {{ \Carbon\Carbon::parse($date)->format(date_fmt()) }}</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <tbody>
                @foreach($registered as $att)
                <tr>
                    <td>{{ $att->employee->name }}</td>
                    <td><span class="badge bg-{{ $statusColors[$att->status] }}">{{ __('app.' . ($att->status === "vacation" ? "on_vacation" : $att->status)) }}</span></td>
                    <td class="text-center">
                        <form action="{{ route("attendance.destroy", $att) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete') }}?">
                            @csrf @method("DELETE")
                            <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
