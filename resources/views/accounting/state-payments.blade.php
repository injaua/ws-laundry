@extends("layouts.admin")
@section("title", __('app.state_payments'))
@section("page_title", __('app.state_payments'))
@section("page_subtitle", __('app.state_payments_subtitle'))

@section("content")
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("accounting.state-payments") }}" method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">{{ __('app.period_month') }}</label>
                <select name="month" class="form-select">
                    @foreach(__('app.months_short') as $i => $label)
                        <option value="{{ $i + 1 }}" {{ $month === $i + 1 ? "selected" : "" }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">{{ __('app.period_year') }}</label>
                <input type="number" name="year" class="form-control w-7rem" min="2000" max="2100" value="{{ $year }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="bi bi-search"></i> {{ __('app.show') }}</button>
            </div>
        </form>
    </div>
</div>

@php($totalDue = \App\Support\Money::add(max(0, $monthVat), $inss["total"]))
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ __('app.vat_to_pay') }} — {{ sprintf("%02d/%04d", $month, $year) }}</div>
                <div class="fs-4 fw-bold">{{ money(abs($monthVat)) }}</div>
                <div class="small {{ $monthVat < 0 ? "text-warning" : "text-muted" }}">
                    {{ $monthVat < 0 ? __('app.vat_to_recover') : __('app.state_due_until', ["date" => $vatDue->format(date_fmt())]) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ __('app.inss_to_pay') }} — {{ sprintf("%02d/%04d", $month, $year) }}</div>
                <div class="fs-4 fw-bold">{{ money($inss["total"]) }}</div>
                <div class="small text-muted">{{ __('app.state_due_until', ["date" => $inssDue->format(date_fmt())]) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-primary">
            <div class="card-body">
                <div class="text-muted small">{{ __('app.state_total_due') }}</div>
                <div class="fs-4 fw-bold text-primary">{{ money($totalDue) }}</div>
                <div class="small text-muted">{{ __('app.state_paid_in_month') }}: {{ money(\App\Support\Money::add($paidVat, $paidInss)) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-bold"><i class="bi bi-percent"></i> {{ __('app.vat') }}</div>
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <tbody>
                        <tr>
                            <td class="fw-medium">4.4.3.3</td>
                            <td>{{ __('app.output_vat_month') }}</td>
                            <td class="text-end">{{ money($outputVat) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-medium">4.4.3.2</td>
                            <td>{{ __('app.input_vat_month') }}</td>
                            <td class="text-end">− {{ money($inputVat) }}</td>
                        </tr>
                        <tr class="{{ $monthVat > 0 ? "table-dark" : ($monthVat < 0 ? "table-warning" : "table-light") }} fw-bold">
                            <td colspan="2">{{ $monthVat >= 0 ? __('app.vat_to_pay') : __('app.vat_to_recover') }}</td>
                            <td class="text-end">{{ money(abs($monthVat)) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-medium">4.4.3.7</td>
                            <td>{{ __('app.vat_payable_balance') }}</td>
                            <td class="text-end">{{ money($vatPayableBalance) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">
                <i class="bi bi-info-circle"></i> {{ __('app.state_vat_help') }}
                <a href="{{ route("accounting.vat-settlement", ["month" => $month, "year" => $year]) }}">{{ __('app.vat_settlement') }}</a>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header fw-bold d-flex justify-content-between">
                <span><i class="bi bi-people"></i> {{ __('app.inss') }}</span>
                <span class="small fw-normal text-muted">{{ __('app.inss_rates', ["employee" => num($employeeRate), "employer" => num($employerRate)]) }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('app.employee') }}</th>
                            <th class="text-end">{{ __('app.inss_base') }}</th>
                            <th class="text-end">{{ __('app.inss_employee_share') }}</th>
                            <th class="text-end">{{ __('app.inss_employer_share') }}</th>
                            <th class="text-end">{{ __('app.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inssRows as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ num($row->base) }}</td>
                            <td class="text-end">{{ num($row->employee) }}</td>
                            <td class="text-end">{{ num($row->employer) }}</td>
                            <td class="text-end fw-medium">{{ num($row->total) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-4 text-muted">{{ __('app.inss_no_salaries') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if($inssRows->isNotEmpty())
                    <tfoot>
                        <tr class="table-dark fw-bold">
                            <td>{{ __('app.inss_to_pay') }}</td>
                            <td class="text-end">{{ num($inss["base"]) }}</td>
                            <td class="text-end">{{ num($inss["employee"]) }}</td>
                            <td class="text-end">{{ num($inss["employer"]) }}</td>
                            <td class="text-end">{{ money($inss["total"]) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
            <div class="card-footer small text-muted">
                <i class="bi bi-info-circle"></i> {{ __('app.state_inss_help') }}
                @if(abs($inssBalance) >= 0.005)
                    <br>{{ __('app.inss_account_balance') }}: <strong>{{ money($inssBalance) }}</strong>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-bold">{{ __('app.state_payments_made') }} — {{ sprintf("%02d/%04d", $month, $year) }}</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.date') }}</th>
                    <th>{{ __('app.entry_number') }}</th>
                    <th>{{ __('app.description') }}</th>
                    <th>{{ __('app.account') }}</th>
                    <th class="text-end">{{ __('app.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->entry_date)->format(date_fmt()) }}</td>
                    <td class="text-muted">{{ $row->entry_number }}</td>
                    <td>{{ $row->description }}</td>
                    <td><span class="text-muted">{{ $row->code }}</span> {{ $row->name }}</td>
                    <td class="text-end">{{ money($row->amount) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">{{ __('app.state_no_payments') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted"><i class="bi bi-info-circle"></i> {{ __('app.state_payments_help') }}</div>
</div>
@endsection
