@extends("layouts.admin")
@section("title", __('app.salaries'))
@section("page_title", __('app.payslips'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $salaries->total()]) }}</span>
    <a href="{{ route("salaries.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_payslip') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("salaries.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <select name="employee_id" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.employees') }}</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request("employee_id") == $employee->id ? "selected" : "" }}>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="month" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.period_month') }}</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ request("month") == $m ? "selected" : "" }}>{{ sprintf("%02d", $m) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="year" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.period_year') }}</option>
                        @for($y = now()->year; $y >= now()->year - 3; $y--)
                            <option value="{{ $y }}" {{ request("year") == $y ? "selected" : "" }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("salaries.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.employee') }}</th>
                        <th>{{ __('app.period_month') }}/{{ __('app.period_year') }}</th>
                        <th class="text-end">{{ __('app.base_salary') }}</th>
                        <th class="text-end">{{ __('app.bonus') }}</th>
                        <th class="text-end">{{ __('app.deduction') }}</th>
                        <th class="text-end">{{ __('app.absence_deduction') }}</th>
                        <th class="text-end">{{ __('app.net_salary') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
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
                        <td>{{ $salary->periodLabel() }}</td>
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
                        <td class="text-center">
                            @cannot("update", $salary)
                                <span class="btn btn-sm btn-outline-secondary disabled" title="{{ __('app.record_of_another_user') }}"><i class="bi bi-lock"></i></span>
                            @elseif($salary->isPaid())
                                <form action="{{ route("salaries.unpay", $salary) }}" method="POST" class="d-inline" data-confirm="{{ __('app.unpay') }}?">
                                    @csrf @method("PATCH")
                                    <button class="btn btn-sm btn-outline-warning" title="{{ __('app.unpay') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                                </form>
                            @else
                                <form action="{{ route("salaries.pay", $salary) }}" method="POST" class="d-inline">
                                    @csrf @method("PATCH")
                                    <button class="btn btn-sm btn-outline-success" title="{{ __('app.mark_paid') }}"><i class="bi bi-check2-circle"></i></button>
                                </form>
                                <a href="{{ route("salaries.edit", $salary) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route("salaries.destroy", $salary) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_payslip_confirm') }}">
                                    @csrf @method("DELETE")
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="bi bi-cash-stack fs-1 d-block mb-2"></i>
                            {{ __('app.no_salaries') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $salaries->links() }}</div>
@endsection
