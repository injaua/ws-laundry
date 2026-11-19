@extends("layouts.admin")
@section("title", __('app.attendance_daily'))
@section("page_title", __('app.attendance'))

@section("content")
@php
    $statusColors = ["present" => "success", "absent" => "danger", "late" => "warning", "excused" => "info", "vacation" => "primary"];
    $statusLetters = [
        "present" => __('app.status_letter_present'),
        "absent" => __('app.status_letter_absent'),
        "late" => __('app.status_letter_late'),
        "excused" => __('app.status_letter_excused'),
        "vacation" => __('app.status_letter_vacation'),
    ];
@endphp
<div class="page-title">
    <span>{{ \Carbon\Carbon::parse($dates[0])->locale(app()->getLocale())->isoFormat("LL") }} — {{ \Carbon\Carbon::parse($endDate)->locale(app()->getLocale())->isoFormat("LL") }}</span>
</div>

<div class="card">
    <div class="card-body p-0">
        <form action="{{ route("attendance.daily") }}" method="GET" class="p-3 border-bottom row g-2 align-items-end">
            <input type="hidden" name="days" value="{{ $days }}">
            <input type="hidden" name="date" value="{{ $endDate }}">
            <div class="col-md-auto">
                <label class="form-label small text-muted mb-1">{{ __('app.employee') }}</label>
                <select name="employee_id" class="form-select form-select-sm minw-220px">
                    <option value="">{{ __('app.all_employees') }}</option>
                    @foreach($allEmployees as $e)
                        <option value="{{ $e->id }}" {{ (int) $employeeId === $e->id ? "selected" : "" }}>{{ $e->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> {{ __('app.summary_apply') }}</button>
                <a href="{{ route("attendance.daily") }}" class="btn btn-sm btn-outline-secondary">{{ __('app.attendance_daily') }}</a>
            </div>
            <div class="col-md d-flex justify-content-end flex-wrap gap-1">
                @foreach([7, 15, 30, 90] as $n)
                    <a href="{{ route("attendance.daily", array_filter(["days" => $n, "employee_id" => $employeeId ?: null, "date" => $endDate])) }}"
                       class="btn btn-sm {{ $days === $n ? "btn-primary" : "btn-outline-primary" }}">{{ $n }} {{ __('app.days') }}</a>
                @endforeach
            </div>
        </form>

        @if($selectedEmployee)
        <div class="px-3 py-2 border-bottom d-flex flex-wrap gap-2 align-items-center">
            <strong>{{ __('app.attendance_of_employee_on', ["name" => $selectedEmployee->name, "date" => \Carbon\Carbon::parse($endDate)->format(date_fmt())]) }}:</strong>
            @if($selectedStatus)
                <span class="badge bg-{{ $statusColors[$selectedStatus] }}">{{ __('app.' . ($selectedStatus === "vacation" ? "on_vacation" : $selectedStatus)) }}</span>
            @else
                <span class="text-muted">{{ __('app.no_record') }}</span>
            @endif
        </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0 text-center align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-start sticky-col minw-180px">{{ __('app.employee') }}</th>
                        @foreach($dates as $d)
                            @php $day = \Carbon\Carbon::parse($d); @endphp
                            <th class="small {{ $day->isSunday() ? "bg-light" : "" }} {{ $d === $endDate ? "fw-bold border-start border-2 border-primary" : "" }} minw-34px">
                                {{ $day->format("d/m") }}
                            </th>
                        @endforeach
                        <th class="text-center minw-80px">{{ __('app.attendance_rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                    @php
                        $rowMap = $byEmployee->get($employee->id, collect());
                        $count = fn ($st) => $rowMap->filter(fn ($s) => $s === $st)->count();
                        $present = $count("present");
                        $absent = $count("absent");
                        $late = $count("late");
                        $expected = $present + $absent + $late;
                        $rate = $expected > 0 ? round(($present + $late) * 100 / $expected, 1) : null;
                        $rateColor = $rate === null ? "secondary" : ($rate >= 90 ? "success" : ($rate >= 70 ? "warning" : "danger"));
                    @endphp
                    <tr>
                        <td class="text-start fw-medium text-truncate sticky-col mw-240px">
                            <a href="{{ route("employees.show", $employee) }}" class="text-decoration-none">{{ $employee->name }}</a>
                        </td>
                        @foreach($dates as $d)
                            @php $st = $rowMap[$d] ?? null; @endphp
                            <td class="{{ $d === $endDate ? "border-start border-2 border-primary" : "" }} p-tight">
                                @if($st)
                                    <span class="badge rounded-pill bg-{{ $statusColors[$st] }}" title="{{ __('app.' . ($st === "vacation" ? "on_vacation" : $st)) }}">{{ $statusLetters[$st] }}</span>
                                @endif
                            </td>
                        @endforeach
                        <td>
                            <span class="badge bg-{{ $rateColor }}">{{ $rate === null ? "—" : number_format($rate, 1, ",", "") . "%" }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($dates) + 2 }}" class="text-center py-4 text-muted">{{ __('app.no_attendance') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top d-flex flex-wrap gap-3 align-items-center">
            <span class="small text-muted">{{ __('app.legend') }}:</span>
            @foreach($statusLetters as $st => $letter)
                <span class="small"><span class="badge rounded-pill bg-{{ $statusColors[$st] }}">{{ $letter }}</span> {{ __('app.' . ($st === "vacation" ? "on_vacation" : $st)) }}</span>
            @endforeach
            <span class="small text-muted">— {{ __('app.no_record') }}</span>
        </div>
    </div>
</div>
@endsection
