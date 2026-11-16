@extends("layouts.admin")
@section("title", __('app.demonstracao_resultados'))
@section("page_title", __('app.demonstracao_resultados'))

@section("content")
@php($fmt = fn ($v) => number_format($v, 0, ".", ","))
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("accounting.demonstracao-resultados") }}" method="GET" class="row g-2 align-items-end">
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
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:110px">{{ __('app.account_code') }}</th>
                    <th>{{ __('app.account_name') }}</th>
                    <th class="text-end" style="width:140px">{{ __('app.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @if($rendimentos->isEmpty() && $gastos->isEmpty())
                <tr>
                    <td colspan="3" class="text-center py-4 text-muted">
                        <i class="bi bi-journal-text fs-1 d-block mb-2"></i>
                        {{ __('app.no_journal_entries') }}
                    </td>
                </tr>
                @endif

                @if($rendimentos->isNotEmpty())
                <tr class="table-dark fw-bold"><td colspan="3">{{ __('app.rendimentos_ganhos') }}</td></tr>
                @foreach($rendimentos as $section)
                <tr class="table-light fw-semibold"><td colspan="2">{{ $section->code }} — {{ $section->label }}</td></tr>
                @foreach($section->rows as $row)
                <tr>
                    <td class="fw-medium ps-3">{{ $row->code }}</td>
                    <td class="ps-4">{{ $row->name }}</td>
                    <td class="text-end">{{ $fmt($row->balance) }}</td>
                </tr>
                @endforeach
                <tr class="fw-semibold">
                    <td colspan="2" class="ps-3">{{ __('app.total') }} {{ mb_strtolower($section->label) }}</td>
                    <td class="text-end">{{ $fmt($section->subtotal) }}</td>
                </tr>
                @endforeach
                <tr class="fw-bold">
                    <td colspan="2">{{ __('app.total_rendimentos') }}</td>
                    <td class="text-end">{{ $fmt($totals["rendimentos"]) }}</td>
                </tr>
                @endif

                @if($gastos->isNotEmpty())
                <tr class="table-dark fw-bold"><td colspan="3">{{ __('app.gastos_perdas') }}</td></tr>
                @foreach($gastos as $section)
                <tr class="table-light fw-semibold"><td colspan="2">{{ $section->code }} — {{ $section->label }}</td></tr>
                @foreach($section->rows as $row)
                <tr>
                    <td class="fw-medium ps-3">{{ $row->code }}</td>
                    <td class="ps-4">{{ $row->name }}</td>
                    <td class="text-end">{{ $fmt($row->balance) }}</td>
                </tr>
                @endforeach
                <tr class="fw-semibold">
                    <td colspan="2" class="ps-3">{{ __('app.total') }} {{ mb_strtolower($section->label) }}</td>
                    <td class="text-end">{{ $fmt($section->subtotal) }}</td>
                </tr>
                @endforeach
                <tr class="fw-bold">
                    <td colspan="2">{{ __('app.total_gastos') }}</td>
                    <td class="text-end">{{ $fmt($totals["gastos"]) }}</td>
                </tr>
                @endif

                @if($rendimentos->isNotEmpty() || $gastos->isNotEmpty())
                <tr class="{{ $totals['resultado'] < 0 ? 'table-danger' : 'table-success' }} fw-bold">
                    <td colspan="2">{{ __('app.resultado_liquido') }}</td>
                    <td class="text-end">{{ $fmt($totals["resultado"]) }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
