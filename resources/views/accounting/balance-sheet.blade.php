@extends("layouts.admin")
@section("title", __('app.balance_sheet'))
@section("page_title", __('app.balance_sheet'))

@section("content")
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("accounting.balance-sheet") }}" method="GET" class="row g-2 align-items-end">
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
    {{ __('app.balance_sheet_unbalanced', ["amount" => num($totals["assets"] - $totals["liabilities_equity"])]) }}
</div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-bold">{{ __('app.assets') }}</div>
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <tbody>
                        @forelse($assets as $row)
                        <tr>
                            <td class="fw-medium">{{ $row->code }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ num($row->balance) }}</td>
                        </tr>
                        @empty
                        <tr><td class="text-center py-4 text-muted">{{ __('app.no_journal_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if($assets->isNotEmpty())
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="2">{{ __('app.total_assets') }}</td>
                            <td class="text-end">{{ num($totals["assets"]) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-bold">{{ __('app.liabilities_and_equity') }}</div>
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <tbody>
                        @if($liabilities->isNotEmpty())
                        <tr class="table-light fw-semibold"><td colspan="3">{{ __('app.liabilities') }}</td></tr>
                        @foreach($liabilities as $row)
                        <tr>
                            <td class="fw-medium">{{ $row->code }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ num($row->balance) }}</td>
                        </tr>
                        @endforeach
                        <tr class="fw-semibold">
                            <td colspan="2" class="ps-3">{{ __('app.total_liabilities') }}</td>
                            <td class="text-end">{{ num($totals["liabilities"]) }}</td>
                        </tr>
                        @endif

                        <tr class="table-light fw-semibold"><td colspan="3">{{ __('app.equity') }}</td></tr>
                        @foreach($capital as $row)
                        <tr>
                            <td class="fw-medium">{{ $row->code }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="text-end">{{ num($row->balance) }}</td>
                        </tr>
                        @endforeach
                        <tr>
                            <td class="fw-medium">8.8</td>
                            <td>{{ __('app.net_result_for_period') }}</td>
                            <td class="text-end {{ $netResult < 0 ? "text-danger" : "" }}">{{ num($netResult) }}</td>
                        </tr>
                        <tr class="fw-semibold">
                            <td colspan="2" class="ps-3">{{ __('app.total_equity') }}</td>
                            <td class="text-end">{{ num($totals["capital"]) }}</td>
                        </tr>

                        <tr class="table-dark fw-bold">
                            <td colspan="2">{{ __('app.total_liabilities_and_equity') }}</td>
                            <td class="text-end">{{ num($totals["liabilities_equity"]) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($balanced && ($assets->isNotEmpty() || $liabilities->isNotEmpty() || $capital->isNotEmpty()))
<div class="text-success small mt-2"><i class="bi bi-check-circle"></i> {{ __('app.balance_sheet_balanced') }}</div>
@endif
@endsection
