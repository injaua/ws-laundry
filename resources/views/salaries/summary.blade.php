@extends("layouts.admin")
@section("title", __('app.salary_summary'))
@section("page_title", __('app.salary_summary'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $salaries->count()]) }}</span>
    <a href="{{ route("salaries.index") }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-list"></i> {{ __('app.payslips') }}
    </a>
</div>

<div class="card mb-3">
    <div class="card-body p-3">
        <form action="{{ route("salaries.summary") }}" method="GET" class="row g-2">
            <div class="col-md-2">
                <select name="month" class="form-select" data-auto-submit>
                    <option value="">{{ __('app.period_month') }}</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $month == $m ? "selected" : "" }}>{{ sprintf("%02d", $m) }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
                <select name="year" class="form-select" data-auto-submit>
                    <option value="">{{ __('app.period_year') }}</option>
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? "selected" : "" }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                <a href="{{ route("salaries.summary") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="small text-muted">{{ __('app.month_total') }}</div>
                <div class="fs-3 fw-bold">{{ money($totalPaid + $totalPending) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="small text-muted">{{ __('app.total_paid_salaries') }}</div>
                <div class="fs-3 fw-bold text-success">{{ money($totalPaid) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="small text-muted">{{ __('app.total_pending_salaries') }}</div>
                <div class="fs-3 fw-bold text-warning">{{ money($totalPending) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-cash-coin me-1"></i> {{ __('app.salary_summary') }} — {{ sprintf("%02d/%04d", $month, $year) }}
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.employee') }}</th>
                    <th class="text-end">{{ __('app.base_salary') }}</th>
                    <th class="text-end">{{ __('app.bonus') }}</th>
                    <th class="text-end">{{ __('app.deduction') }}</th>
                    <th class="text-end">{{ __('app.absence_deduction') }}</th>
                    <th class="text-end">{{ __('app.net_salary') }}</th>
                    <th>{{ __('app.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salaries as $salary)
                <tr>
                    <td class="fw-medium">
                        @if(auth()->user()->isManager())
                        <a href="{{ route("employees.show", $salary->employee) }}" class="text-decoration-none">{{ $salary->employee->name }}</a>
                        @else
                        {{ $salary->employee->name }}
                        @endif
                    </td>
                    <td class="text-end">{{ num($salary->base_salary) }}</td>
                    <td class="text-end text-success">{{ num($salary->bonus) }}</td>
                    <td class="text-end text-danger">{{ num($salary->deduction) }}</td>
                    <td class="text-end text-danger">{{ num($salary->absence_deduction) }}</td>
                    <td class="text-end fw-semibold">{{ money($salary->net_salary) }}</td>
                    <td>
                        <span class="badge bg-{{ $salary->isPaid() ? "success" : "secondary" }}">
                            {{ $salary->isPaid() ? __('app.paid') : __('app.pending') }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bi bi-cash-stack fs-1 d-block mb-2"></i>
                        {{ __('app.no_salaries') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($salaries->isNotEmpty())
            <tfoot>
                <tr class="table-light fw-semibold">
                    <td>{{ __('app.month_total') }}</td>
                    <td class="text-end">{{ num($salaries->sum("base_salary")) }}</td>
                    <td class="text-end">{{ num($salaries->sum("bonus")) }}</td>
                    <td class="text-end">{{ num($salaries->sum("deduction")) }}</td>
                    <td class="text-end">{{ num($salaries->sum("absence_deduction")) }}</td>
                    <td class="text-end">{{ money($salaries->sum("net_salary")) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
