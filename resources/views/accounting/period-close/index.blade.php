<?php
?>
@extends("layouts.admin")

@section("title", __('app.accounting_close'))
@section("page_title", __('app.accounting_close'))

@section("content")
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ __('app.monthly_close') }} — {{ $year }}</span>
                <form method="GET" class="d-flex gap-2">
                    <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="form-control form-control-sm w-7rem" data-auto-submit>
                    <noscript><button class="btn btn-sm btn-primary">{{ __('app.filter') }}</button></noscript>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('app.month') }}</th>
                                <th class="text-end">{{ __('app.journal') }}</th>
                                <th>{{ __('app.vat_settlement') }}</th>
                                <th>{{ __('app.status') }}</th>
                                <th class="text-end">{{ __('app.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($months as $m => $row)
                            <tr>
                                <td class="small fw-semibold">{{ \Carbon\Carbon::create($year, $m, 1)->translatedFormat("F") }}/{{ $year }}</td>
                                <td class="text-end small">{{ $row["entries"] }}</td>
                                <td class="small">
                                    @if($row["vat_settled"])
                                        <span class="badge bg-success-subtle text-success">{{ __('app.vat_settled') }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">{{ __('app.vat_not_settled') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row["period"]?->isClosed())
                                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-lock"></i> {{ __('app.status_closed') }}</span>
                                    @elseif($row["period"] && $row["period"]->status === "reopened")
                                        <span class="badge bg-warning-subtle text-warning">{{ __('app.status_reopened') }}</span>
                                    @elseif($row["closable"])
                                        <span class="badge bg-info-subtle text-info">{{ __('app.status_pending') }}</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">{{ __('app.status_open') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($row["closable"] && !$row["period"]?->isClosed())
                                        <form action="{{ route("accounting.period-close.close") }}" method="POST" class="d-inline" data-confirm="{{ __('app.period_close_confirm', ["period" => sprintf("%02d/%04d", $m, $year)]) }}">
                                            @csrf
                                            <input type="hidden" name="month" value="{{ $m }}">
                                            <input type="hidden" name="year" value="{{ $year }}">
                                            <button class="btn btn-sm btn-primary"><i class="bi bi-lock"></i> {{ __('app.close_month') }}</button>
                                        </form>
                                    @endif
                                    @if($row["period"]?->isClosed())
                                        @if($row["archive"])
                                            <a href="{{ route("accounting.period-close.archive", $row["archive"]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-text"></i> {{ __('app.view') }}</a>
                                            <a href="{{ route("accounting.period-close.export", $row["archive"]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> Excel</a>
                                        @endif
                                        <form action="{{ route("accounting.period-close.reopen", $row["period"]) }}" method="POST" class="d-inline" data-confirm="{{ __('app.period_reopen_confirm') }}">
                                            @csrf @method("PATCH")
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-unlock"></i> {{ __('app.accounting_reopen') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle"></i> {{ __('app.period_rules') }}</div>
            <div class="card-body small">
                <ul class="mb-0 ps-3">
                    <li>{{ __('app.period_rule_sequential') }}</li>
                    <li>{{ __('app.period_rule_lock') }}</li>
                    <li>{{ __('app.period_rule_archive') }}</li>
                </ul>
                @if($next)
                    <hr>
                    <div class="text-muted">{{ __('app.first_pending_month') }}: <strong>{{ sprintf("%02d/%04d", $next["month"], $next["year"]) }}</strong></div>
                @else
                    <hr>
                    <div class="text-success"><i class="bi bi-check-circle"></i> {{ __('app.period_all_closed') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="bi bi-archive"></i> {{ __('app.trial_balance_archive') }}</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.period') }}</th>
                        <th>{{ __('app.created_by') }}</th>
                        <th>{{ __('app.date') }}</th>
                        <th class="text-end">{{ __('app.total_debit') }}</th>
                        <th class="text-end">{{ __('app.total_credit') }}</th>
                        <th class="text-end">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allArchives as $archive)
                    <tr>
                        <td class="small fw-semibold">{{ $archive->label() }}</td>
                        <td class="small">{{ $archive->period?->closer?->name ?? $archive->creator?->name ?? "—" }}</td>
                        <td class="small">{{ $archive->created_at?->format(datetime_fmt()) }}</td>
                        <td class="text-end small">{{ num($archive->total_debit) }}</td>
                        <td class="text-end small">{{ num($archive->total_credit) }}</td>
                        <td class="text-end">
                            <a href="{{ route("accounting.period-close.archive", $archive) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> {{ __('app.view') }}</a>
                            <a href="{{ route("accounting.period-close.export", $archive) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> Excel</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">{{ __('app.no_records') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
