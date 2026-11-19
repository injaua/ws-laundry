@extends("layouts.admin")
@section("title", __('app.income_statement'))
@section("page_title", __('app.income_statement'))

@section("content")
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("accounting.income-statement") }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">{{ __('app.from_date') }}</label>
                <input type="date" name="from" class="form-control" value="{{ $from->format("Y-m-d") }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ __('app.to_date') }}</label>
                <input type="date" name="to" class="form-control" value="{{ $to->format("Y-m-d") }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="bi bi-search"></i> {{ __('app.show') }}</button>
                <x-export-buttons class="ms-1" />
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th class="w-110px">{{ __('app.account_code') }}</th>
                    <th>{{ __('app.account_name') }}</th>
                    <th class="text-end w-140px">{{ __('app.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @if($income->isEmpty() && $expenses->isEmpty())
                <tr>
                    <td colspan="3" class="text-center py-4 text-muted">
                        <i class="bi bi-journal-text fs-1 d-block mb-2"></i>
                        {{ __('app.no_journal_entries') }}
                    </td>
                </tr>
                @endif

                @if($income->isNotEmpty())
                <tr class="table-dark fw-bold"><td colspan="3">{{ __('app.income_and_gains') }}</td></tr>
                @foreach($income as $section)
                <tr class="table-light fw-semibold"><td colspan="2">{{ $section->code }} — {{ $section->label }}</td></tr>
                @foreach($section->rows as $row)
                <tr>
                    <td class="fw-medium ps-3">{{ $row->code }}</td>
                    <td class="ps-4">{{ $row->name }}</td>
                    <td class="text-end">{{ num($row->balance) }}</td>
                </tr>
                @endforeach
                <tr class="fw-semibold">
                    <td colspan="2" class="ps-3">{{ __('app.total') }} {{ mb_strtolower($section->label) }}</td>
                    <td class="text-end">{{ num($section->subtotal) }}</td>
                </tr>
                @endforeach
                <tr class="fw-bold">
                    <td colspan="2">{{ __('app.total_income') }}</td>
                    <td class="text-end">{{ num($totals["income"]) }}</td>
                </tr>
                @endif

                @if($expenses->isNotEmpty())
                <tr class="table-dark fw-bold"><td colspan="3">{{ __('app.expenses_and_losses') }}</td></tr>
                @foreach($expenses as $section)
                <tr class="table-light fw-semibold"><td colspan="2">{{ $section->code }} — {{ $section->label }}</td></tr>
                @foreach($section->rows as $row)
                <tr>
                    <td class="fw-medium ps-3">{{ $row->code }}</td>
                    <td class="ps-4">{{ $row->name }}</td>
                    <td class="text-end">{{ num($row->balance) }}</td>
                </tr>
                @endforeach
                <tr class="fw-semibold">
                    <td colspan="2" class="ps-3">{{ __('app.total') }} {{ mb_strtolower($section->label) }}</td>
                    <td class="text-end">{{ num($section->subtotal) }}</td>
                </tr>
                @endforeach
                <tr class="fw-bold">
                    <td colspan="2">{{ __('app.total_expenses_and_losses') }}</td>
                    <td class="text-end">{{ num($totals["expenses"]) }}</td>
                </tr>
                @endif

                @if($income->isNotEmpty() || $expenses->isNotEmpty())
                <tr class="{{ $totals['net_result'] < 0 ? 'table-danger' : 'table-success' }} fw-bold">
                    <td colspan="2">{{ __('app.net_result') }}</td>
                    <td class="text-end">{{ num($totals["net_result"]) }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
