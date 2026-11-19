@extends("layouts.document")

{{-- Payslip (recibo de vencimento) of one employee for one month. --}}
@php($employee = $salary->employee)

@section("title", __('app.payslip') . " " . $employee->name . " " . $salary->periodLabel())
@section("doc_type", __('app.payslip'))
@section("doc_meta")
    <div><strong>{{ $salary->periodLabel() }}</strong></div>
    @if($salary->isPaid())
        <div>{{ __('app.paid') }}{{ $salary->paid_at ? ": " . $salary->paid_at->format(date_fmt()) : "" }}</div>
    @else
        <div class="muted">{{ __('app.pending') }}</div>
    @endif
@endsection
@section("no_bank_details", "1")

@section("content")
<div class="parties">
    <div>
        <div class="label">{{ __('app.employee') }}</div>
        <strong>{{ $employee->name }}</strong>
        @if($employee->position)<div>{{ $employee->position }}</div>@endif
        @if($employee->bi_number)<div class="muted">{{ __('app.bi_number') }}: {{ $employee->bi_number }}</div>@endif
    </div>
    <div>
        @if($employee->hire_date)<div>{{ __('app.hire_date') }}: {{ $employee->hire_date->format(date_fmt()) }}</div>@endif
        @if($employee->contract_type)<div class="muted">{{ __('app.contract_type') }}: {{ __('app.contract_' . $employee->contract_type) }}</div>@endif
    </div>
</div>

<table>
    <thead>
        <tr><th>{{ __('app.description') }}</th><th class="r">{{ __('app.earnings') }}</th><th class="r">{{ __('app.deduction') }}</th></tr>
    </thead>
    <tbody>
        <tr><td>{{ __('app.base_salary') }}</td><td class="r">{{ num($salary->base_salary) }}</td><td></td></tr>
        @if((float) $salary->bonus > 0)<tr><td>{{ __('app.bonus') }}</td><td class="r">{{ num($salary->bonus) }}</td><td></td></tr>@endif
        @if((float) $salary->absence_deduction > 0)<tr><td>{{ __('app.absence_deduction') }}</td><td></td><td class="r">{{ num($salary->absence_deduction) }}</td></tr>@endif
        @if((float) $salary->deduction > 0)<tr><td>{{ __('app.deduction') }}</td><td></td><td class="r">{{ num($salary->deduction) }}</td></tr>@endif
    </tbody>
</table>

<table class="totals">
    <tr class="grand"><td>{{ __('app.net_salary') }}</td><td class="r">{{ money($salary->net_salary) }}</td></tr>
</table>

@if($salary->notes)
    <div class="note"><div class="label">{{ __('app.notes') }}</div>{{ $salary->notes }}</div>
@endif

<table class="signatures">
    <tr>
        <td><div class="line">{{ __('app.employer_signature') }}</div></td>
        <td><div class="line">{{ __('app.employee_signature') }}</div></td>
    </tr>
</table>
@endsection
