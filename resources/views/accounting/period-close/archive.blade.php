<?php
?>
@extends("layouts.admin")

@section("title", __('app.trial_balance') . " " . $archive->label())
@section("page_title", __('app.trial_balance_archived') . " — " . $archive->label())

@section("content")
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small">
            <div class="fw-semibold mb-1"><i class="bi bi-lock"></i> {{ __('app.period') }}: {{ $archive->label() }}</div>
            <div class="text-muted">
                {{ $archive->from_date?->format(date_fmt()) }} — {{ $archive->to_date?->format(date_fmt()) }}
                · {{ __('app.archive_closed_by') }}: {{ $archive->period?->closer?->name ?? $archive->creator?->name ?? "—" }}
                · {{ __('app.archive_closed_on') }}: {{ $archive->created_at?->format(datetime_fmt()) }}
            </div>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <a href="{{ route("accounting.period-close") }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.back') }}</a>
            <a href="{{ route("accounting.period-close.export", $archive) }}" class="btn btn-sm btn-success"><i class="bi bi-download"></i> {{ __('app.export') }} Excel</a>
            <button data-print class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i> {{ __('app.print') }}</button>
        </div>
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
                    </tr>
                </thead>
                <tbody>
                    @forelse($archive->lines as $line)
                    <tr>
                        <td class="fw-medium">{{ $line->account_code }}</td>
                        <td>{{ $line->account_name }} <span class="badge bg-light text-dark border ms-1">{{ __('app.account_type_' . $line->account_type) }}</span></td>
                        <td class="text-end">{{ num($line->total_debit) }}</td>
                        <td class="text-end">{{ num($line->total_credit) }}</td>
                        <td class="text-end fw-semibold">{{ $line->balance_debit > 0 ? num($line->balance_debit) : "-" }}</td>
                        <td class="text-end fw-semibold">{{ $line->balance_credit > 0 ? num($line->balance_credit) : "-" }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">{{ __('app.no_records') }}</td>
                    </tr>
                    @endforelse
                </tbody>
                @if($archive->lines->isNotEmpty())
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="2">{{ __('app.total') }}</td>
                        <td class="text-end">{{ num($archive->total_debit) }}</td>
                        <td class="text-end">{{ num($archive->total_credit) }}</td>
                        <td class="text-end"></td>
                        <td class="text-end"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
