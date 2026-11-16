@extends("layouts.admin")
@section("title", __('app.iva_settlement'))
@section("page_title", __('app.iva_settlement'))

@section("content")
@php($fmt = fn ($v) => number_format($v, 0, ".", ","))
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route("accounting.iva-acerto") }}" method="GET" class="row g-2 align-items-end">
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
                <input type="number" name="year" class="form-control" style="width:7rem" min="2000" max="2100" value="{{ $year }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary"><i class="bi bi-search"></i> {{ __('app.show') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header fw-bold">{{ sprintf("%02d/%04d", $month, $year) }} — {{ __('app.total') }}</div>
    <div class="card-body p-0">
        <table class="table mb-0 align-middle">
            <tbody>
                <tr>
                    <td class="fw-medium">4.4.3.3</td>
                    <td>{{ __('app.iva_liquidado_pending') }}</td>
                    <td class="text-end">{{ $fmt($liquidado) }} Mts</td>
                </tr>
                <tr>
                    <td class="fw-medium">4.4.3.1</td>
                    <td>{{ __('app.iva_suportado_pending') }}</td>
                    <td class="text-end">{{ $fmt($suportado) }} Mts</td>
                </tr>
                <tr class="{{ $saldo > 0 ? "table-dark" : ($saldo < 0 ? "table-warning" : "table-light") }} fw-bold">
                    <td colspan="2">{{ $saldo >= 0 ? __('app.iva_to_pay') : __('app.iva_to_recover') }}</td>
                    <td class="text-end">{{ $fmt(abs($saldo)) }} Mts</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <form action="{{ route("accounting.iva-acerto.settlement") }}" method="POST" onsubmit="return confirm('{{ __('app.generate_settlement') }}?')">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="year" value="{{ $year }}">
            <button type="submit" class="btn btn-primary" {{ abs($liquidado) < 0.005 && abs($suportado) < 0.005 ? "disabled" : "" }}>
                <i class="bi bi-percent"></i> {{ __('app.generate_settlement') }}
            </button>
        </form>
        <div class="form-text mt-2"><i class="bi bi-info-circle"></i> {{ __('app.iva_settlement_help') }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-bold">{{ __('app.iva_settlement_entries') }}</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.date') }}</th>
                    <th>{{ __('app.entry_number') }}</th>
                    <th>{{ __('app.description') }}</th>
                    <th>{{ __('app.account') }}</th>
                    <th class="text-end">{{ __('app.debit') }}</th>
                    <th class="text-end">{{ __('app.credit') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->entry_date)->format("Y-m-d") }}</td>
                    <td class="text-muted">{{ $row->entry_number }}</td>
                    <td>{{ $row->description }}</td>
                    <td><span class="text-muted">{{ $row->code }}</span> {{ $row->name }}</td>
                    <td class="text-end">{{ $row->debit > 0 ? $fmt($row->debit) : "—" }}</td>
                    <td class="text-end">{{ $row->credit > 0 ? $fmt($row->credit) : "—" }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">{{ __('app.no_journal_entries') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
