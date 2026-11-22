@extends("layouts.admin")
@section("title", __('app.tills'))
@section("page_title", __('app.till_history'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $sessions->total()]) }}</span>
    @if($current)
        <a href="{{ route("cash-sessions.show", $current) }}" class="btn btn-success"><i class="bi bi-safe"></i> {{ __('app.my_till') }} ({{ $current->label() }})</a>
    @elseif(auth()->user()->can("open", \App\Models\CashSession::class))
        <a href="{{ route("cash-sessions.current") }}" class="btn btn-primary"><i class="bi bi-unlock"></i> {{ __('app.open_till') }}</a>
    @endif
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("cash-sessions.index") }}" method="GET" class="row g-2 align-items-center">
                @if($isManager)
                <div class="col-md-3">
                    <select name="user_id" class="form-select">
                        <option value="">{{ __('app.all_operators') }}</option>
                        @foreach($operators as $operator)
                            <option value="{{ $operator->id }}" @selected((int) request("user_id") === $operator->id)>{{ $operator->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">{{ __('app.all_statuses') }}</option>
                        <option value="open" @selected(request("status") === "open")>{{ __('app.till_status_open') }}</option>
                        <option value="closed" @selected(request("status") === "closed")>{{ __('app.till_status_closed') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date" class="form-control" value="{{ request_date("date")?->toDateString() }}">
                </div>
                <div class="col-auto">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="with_difference" value="1" id="with_difference" @checked(request()->boolean("with_difference"))>
                        <label class="form-check-label" for="with_difference">{{ __('app.only_with_difference') }}</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.filter') }}</button>
                    <a href="{{ route("cash-sessions.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.till') }}</th>
                        <th>{{ __('app.operator') }}</th>
                        <th>{{ __('app.opened_at') }}</th>
                        <th>{{ __('app.closed_at') }}</th>
                        <th class="text-end">{{ __('app.counted') }}</th>
                        <th class="text-end">{{ __('app.difference') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                    @php($diff = $session->isOpen() ? null : $session->totalDifference())
                    <tr>
                        <td class="fw-medium text-nowrap">{{ $session->label() }}</td>
                        <td>{{ $session->user->name }}</td>
                        <td class="small text-nowrap">{{ $session->opened_at->format(datetime_fmt()) }}</td>
                        <td class="small text-nowrap">{{ $session->closed_at?->format(datetime_fmt()) ?? "-" }}</td>
                        <td class="text-end">{{ $session->isOpen() ? "-" : money($session->counts->sum("counted")) }}</td>
                        <td class="text-end fw-semibold {{ $diff === null ? "" : ($diff < 0 ? "text-danger" : ($diff > 0 ? "text-warning" : "text-success")) }}">
                            {{ $diff === null ? "-" : ($diff > 0 ? "+" : "") . money($diff) }}
                        </td>
                        <td><span class="badge bg-{{ $session->isOpen() ? "success" : "secondary" }}">{{ __('app.till_status_' . $session->status) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route("cash-sessions.show", $session) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-4 text-muted">{{ __('app.no_tills_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sessions->hasPages())
            <div class="p-3">{{ $sessions->links() }}</div>
        @endif
    </div>
</div>
@endsection
