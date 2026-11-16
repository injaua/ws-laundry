@extends("layouts.admin")
@section("title", __('app.attendance_summary'))
@section("page_title", __('app.attendance_summary'))

@section("content")
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <span><i class="bi bi-clipboard-data me-1"></i> {{ __('app.attendance_summary') }}</span>
        <span class="small text-muted">{{ \Carbon\Carbon::parse($summaryStart)->format("d/m/Y") }} — {{ \Carbon\Carbon::parse($summaryEnd)->format("d/m/Y") }}</span>
    </div>
    <div class="card-body p-0">
        <form action="{{ route("attendance.summary") }}" method="GET" class="p-3 border-bottom row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">{{ __('app.summary_by_month') }}</label>
                <input type="month" name="summary_month" class="form-control form-control-sm" value="{{ $summaryMonth }}">
            </div>
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">{{ __('app.summary_from') }}</label>
                <input type="date" name="summary_start" class="form-control form-control-sm" value="{{ $summaryStart }}">
            </div>
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">{{ __('app.summary_to') }}</label>
                <input type="date" name="summary_end" class="form-control form-control-sm" value="{{ $summaryEnd }}">
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> {{ __('app.summary_apply') }}</button>
                <a href="{{ route("attendance.summary") }}" class="btn btn-sm btn-outline-secondary">{{ __('app.summary_this_month') }}</a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.employee') }}</th>
                        <th class="text-center">{{ __('app.present') }}</th>
                        <th class="text-center">{{ __('app.absent') }}</th>
                        <th class="text-center">{{ __('app.late') }}</th>
                        <th class="text-center">{{ __('app.excused') }}</th>
                        <th class="text-center">{{ __('app.on_vacation') }}</th>
                        <th class="text-center">{{ __('app.days_registered') }}</th>
                        <th class="text-center">{{ __('app.attendance_rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary as $row)
                    @php
                        $rate = $row->attendance_rate;
                        $rateColor = $rate === null ? "secondary" : ($rate >= 90 ? "success" : ($rate >= 70 ? "warning" : "danger"));
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-medium">{{ $names[$row->employee_id] ?? "—" }}</td>
                        <td class="text-center">{{ $row->days_present }}</td>
                        <td class="text-center">{{ $row->days_absent }}</td>
                        <td class="text-center">{{ $row->days_late }}</td>
                        <td class="text-center">{{ $row->days_excused }}</td>
                        <td class="text-center">{{ $row->days_vacation }}</td>
                        <td class="text-center">{{ $row->days_total }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $rateColor }}">{{ $rate === null ? "—" : number_format($rate, 1, ",", "") . "%" }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">{{ __('app.no_summary_data') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
