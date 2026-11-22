@extends("layouts.admin")
@section("title", __('app.journal_entries'))
@section("page_title", __('app.journal_entries'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $entries->total()]) }}</span>
    <div class="d-flex gap-2">
        <x-export-buttons />
        <a href="{{ route("accounting.journal-entries.create") }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> {{ __('app.new_journal_entry') }}
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("accounting.journal-entries.index") }}" method="GET" class="row g-2">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search_journal') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="source" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.source') }}</option>
                        @foreach(\App\Models\JournalEntry::SOURCES as $src)
                            <option value="{{ $src }}" {{ request("source") == $src ? "selected" : "" }}>{{ __('app.source_' . $src) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="account_id" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.account_name') }}</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ request("account_id") == $account->id ? "selected" : "" }}>{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="from" class="form-control" value="{{ request_date("from")?->toDateString() }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="to" class="form-control" value="{{ request_date("to")?->toDateString() }}">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("accounting.journal-entries.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.date') }}</th>
                        <th>{{ __('app.entry_number') }}</th>
                        <th>{{ __('app.description') }}</th>
                        <th>{{ __('app.source') }}</th>
                        <th class="text-end">{{ __('app.debit') }}</th>
                        <th class="text-end">{{ __('app.credit') }}</th>
                        <th>{{ __('app.posted_by') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td class="small">{{ $entry->entry_date->format(date_fmt()) }}</td>
                        <td class="fw-medium">{{ $entry->entry_number }}</td>
                        <td>
                            {{ $entry->description }}
                            <div class="small text-muted">
                                @foreach($entry->lines as $line)
                                    {{ $line->account->code }} {{ $line->account->name }}
                                    ({{ $line->debit > 0 ? "D " . num($line->debit) : "C " . num($line->credit) }})@if(!$loop->last), @endif
                                @endforeach
                            </div>
                        </td>
                        <td><span class="badge bg-{{ (["manual" => "primary", "payment" => "success", "expense" => "warning", "credit_sale" => "dark", "cash_session" => "info"][$entry->source] ?? "secondary") }}">{{ __('app.source_' . $entry->source) }}</span></td>
                        <td class="text-end fw-semibold">{{ num($entry->lines->sum("debit")) }}</td>
                        <td class="text-end fw-semibold">{{ num($entry->lines->sum("credit")) }}</td>
                        <td class="small">{{ $entry->poster?->name ?? "-" }}</td>
                        <td class="text-center">
                            @cannot("update", $entry)
                                <span class="btn btn-sm btn-outline-secondary disabled" title="{{ __('app.record_of_another_user') }}">
                                    <i class="bi bi-lock"></i>
                                </span>
                            @else
                            @if($entry->isManual())
                                <a href="{{ route("accounting.journal-entries.edit", $entry) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            @else
                                <span class="btn btn-sm btn-outline-secondary disabled" title="{{ __('app.journal_auto_locked') }}">
                                    <i class="bi bi-pencil"></i>
                                </span>
                            @endif
                            <form action="{{ route("accounting.journal-entries.destroy", $entry) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_journal_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}" {{ $entry->isManual() ? "" : "disabled" }}>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endcannot
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-journal-text fs-1 d-block mb-2"></i>
                            {{ __('app.no_journal_entries') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $entries->links() }}</div>
@endsection
