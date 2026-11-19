@extends("layouts.admin")
@section("title", __('app.trial_balance'))
@section("page_title", __('app.trial_balance'))

@section("content")
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("accounting.trial-balance") }}" method="GET" class="row g-2 align-items-end">
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
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.account_code') }}</th>
                        <th>{{ __('app.account_name') }}</th>
                        <th class="text-end">{{ __('app.debit') }}</th>
                        <th class="text-end">{{ __('app.credit') }}</th>
                        <th class="text-end">{{ __('app.balance_debit') }}</th>
                        <th class="text-end">{{ __('app.balance_credit') }}</th>
                        <th class="text-center">{{ __('app.account_statement') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td class="fw-medium">{{ $row->code }}</td>
                        <td>{{ $row->name }} <span class="badge bg-light text-dark border ms-1">{{ __('app.account_type_' . $row->type) }}</span></td>
                        <td class="text-end">{{ num($row->total_debit) }}</td>
                        <td class="text-end">{{ num($row->total_credit) }}</td>
                        <td class="text-end fw-semibold">{{ $row->balance_debit > 0 ? num($row->balance_debit) : "-" }}</td>
                        <td class="text-end fw-semibold">{{ $row->balance_credit > 0 ? num($row->balance_credit) : "-" }}</td>
                        <td class="text-center">
                            <a href="{{ route("accounting.journal-entries.index", ["account_id" => $row->id, "from" => $from->format("Y-m-d"), "to" => $to->format("Y-m-d")]) }}"
                               class="btn btn-sm btn-outline-primary" title="{{ __('app.account_statement') }}">
                                <i class="bi bi-list-ul"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-journal-text fs-1 d-block mb-2"></i>
                            {{ __('app.no_journal_entries') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="2">{{ __('app.total') }}</td>
                        <td class="text-end">{{ num($totals["debit"]) }}</td>
                        <td class="text-end">{{ num($totals["credit"]) }}</td>
                        <td class="text-end">{{ num($totals["balance_debit"]) }}</td>
                        <td class="text-end">{{ num($totals["balance_credit"]) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
