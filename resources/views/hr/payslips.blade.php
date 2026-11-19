@extends("layouts.admin")
@section("title", __('app.my_payslips'))
@section("page_title", __('app.my_payslips'))

@section("content")
@if(!$employee)
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-person-x fs-1 d-block mb-2"></i>
        {{ __('app.no_employee_record') }}
    </div>
</div>
@else
<div class="card">
    <div class="card-header"><i class="bi bi-cash-coin me-1"></i> {{ $employee->name }} — {{ $employee->position }}</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.period_month') }}/{{ __('app.period_year') }}</th>
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
                        <a href="{{ route("hr.payslip", $salary) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1 ms-1" title="{{ __('app.payslip') }}"><i class="bi bi-file-earmark-pdf"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-4 text-muted">{{ __('app.no_salaries') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if($salaries instanceof \Illuminate\Contracts\Pagination\Paginator)
<div class="mt-3">{{ $salaries->links() }}</div>
@endif
@endif
@endsection
