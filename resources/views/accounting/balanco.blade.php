@extends("layouts.admin")
@section("title", __('app.balanco'))
@section("page_title", __('app.balanco'))

@section("content")
@php($fmt = fn ($v) => number_format($v, 0, ".", ","))
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("accounting.balanco") }}" method="GET" class="row g-2 align-items-end">
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

@if(!$balanced)
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    {{ __('app.balanco_desequilibrado', ["amount" => $fmt($totals["activo"] - $totals["passivo_capital"])]) }}
</div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-bold">{{ __('app.activo') }}</div>
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <tbody>
                        @forelse($activo as $row)
                        <tr>
                            <td class="fw-medium">{{ $row->code }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ $fmt($row->balance) }}</td>
                        </tr>
                        @empty
                        <tr><td class="text-center py-4 text-muted">{{ __('app.no_journal_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if($activo->isNotEmpty())
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="2">{{ __('app.total_activo') }}</td>
                            <td class="text-end">{{ $fmt($totals["activo"]) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-bold">{{ __('app.passivo_capital_proprio') }}</div>
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <tbody>
                        @if($passivo->isNotEmpty())
                        <tr class="table-light fw-semibold"><td colspan="3">{{ __('app.passivo') }}</td></tr>
                        @foreach($passivo as $row)
                        <tr>
                            <td class="fw-medium">{{ $row->code }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ $fmt($row->balance) }}</td>
                        </tr>
                        @endforeach
                        <tr class="fw-semibold">
                            <td colspan="2" class="ps-3">{{ __('app.total_passivo') }}</td>
                            <td class="text-end">{{ $fmt($totals["passivo"]) }}</td>
                        </tr>
                        @endif

                        <tr class="table-light fw-semibold"><td colspan="3">{{ __('app.capital_proprio') }}</td></tr>
                        @foreach($capital as $row)
                        <tr>
                            <td class="fw-medium">{{ $row->code }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ $fmt($row->balance) }}</td>
                        </tr>
                        @endforeach
                        <tr>
                            <td class="fw-medium">8.8</td>
                            <td>{{ __('app.resultado_liquido_periodo') }}</td>
                            <td class="text-end {{ $resultado < 0 ? "text-danger" : "" }}">{{ $fmt($resultado) }}</td>
                        </tr>
                        <tr class="fw-semibold">
                            <td colspan="2" class="ps-3">{{ __('app.total_capital_proprio') }}</td>
                            <td class="text-end">{{ $fmt($totals["capital"]) }}</td>
                        </tr>

                        <tr class="table-dark fw-bold">
                            <td colspan="2">{{ __('app.total_passivo_capital') }}</td>
                            <td class="text-end">{{ $fmt($totals["passivo_capital"]) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($balanced && ($activo->isNotEmpty() || $passivo->isNotEmpty() || $capital->isNotEmpty()))
<div class="text-success small mt-2"><i class="bi bi-check-circle"></i> {{ __('app.balanco_equilibrado') }}</div>
@endif
@endsection
