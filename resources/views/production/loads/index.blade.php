@extends("layouts.admin")
@section("title", __('app.machine_loads'))
@section("page_title", __('app.machine_loads'))
@section("page_subtitle", __('app.machine_loads_subtitle'))

@php($num = fn ($n) => qty($n, 2))

@section("content")
<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap">
        <select name="equipment" class="form-select" data-auto-submit>
            <option value="">{{ __('app.all_machines') }}</option>
            @foreach($machines as $m)
                <option value="{{ $m->id }}" @selected(request('equipment') == $m->id)>{{ $m->name }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select" data-auto-submit>
            <option value="">{{ __('app.all_statuses') }}</option>
            @foreach(\App\Models\MachineLoad::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ __('app.load_status_' . $s) }}</option>
            @endforeach
        </select>
    </form>
    <div class="d-flex gap-2">
        <a href="{{ route('production.board') }}" class="btn btn-outline-secondary"><i class="bi bi-kanban"></i> {{ __('app.production_board') }}</a>
        <a href="{{ route('production.loads.create', ['stage' => 'washing']) }}" class="btn btn-primary"><i class="bi bi-droplet"></i> {{ __('app.new_wash_load') }}</a>
        <a href="{{ route('production.loads.create', ['stage' => 'drying']) }}" class="btn btn-outline-primary"><i class="bi bi-wind"></i> {{ __('app.new_dry_load') }}</a>
    </div>
</div>

@if($running->isNotEmpty())
<div class="row g-2 mb-3">
    @foreach($running as $load)
        <div class="col-md-4">
            <div class="card border-primary h-100">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('production.loads.show', $load) }}" class="fw-semibold">{{ $load->equipment?->name }}</a>
                        <span class="small text-muted">{{ $load->started_at?->diffForHumans(null, true) }}</span>
                    </div>
                    <div class="small">{{ $load->code }} · {{ __('app.status_' . $load->stage) }} · {{ $num($load->weight_kg) }} kg
                        @if($load->utilization() !== null)({{ $load->utilization() }}%)@endif
                    </div>
                    <div class="small text-muted text-truncate">{{ $load->orders->pluck('order_number')->join(', ') }}</div>
                    <form method="POST" action="{{ route('production.loads.finish', $load) }}" class="mt-2">
                        @csrf
                        <button class="btn btn-sm btn-success w-100"><i class="bi bi-check2-circle"></i> {{ __('app.finish_load') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>{{ __('app.load') }}</th><th>{{ __('app.machine') }}</th><th>{{ __('app.stage') }}</th><th>{{ __('app.program') }}</th>
                <th class="text-end">kg</th><th class="text-end">{{ __('app.utilization') }}</th><th>{{ __('app.operator') }}</th><th>{{ __('app.started_at') }}</th><th>{{ __('app.status') }}</th>
            </tr></thead>
            <tbody>
            @forelse($loads as $load)
                <tr>
                    <td><a href="{{ route('production.loads.show', $load) }}">{{ $load->code }}</a> <span class="small text-muted">({{ $load->orders_count }})</span></td>
                    <td>{{ $load->equipment?->name ?? '—' }}</td>
                    <td>{{ __('app.status_' . $load->stage) }}</td>
                    <td class="small">{{ $load->program ?: '—' }}</td>
                    <td class="text-end">{{ $num($load->weight_kg) }}</td>
                    <td class="text-end">
                        @if($load->utilization() !== null)
                            <span class="{{ $load->utilization() > 100 ? 'text-danger fw-semibold' : ($load->utilization() < 60 ? 'text-warning' : '') }}">{{ $load->utilization() }}%</span>
                        @else — @endif
                    </td>
                    <td class="small">{{ $load->employee?->name ?? '—' }}</td>
                    <td class="small">{{ $load->started_at?->format(datetime_fmt()) }}@if($load->durationMinutes() !== null)<span class="text-muted"> · {{ $load->durationMinutes() }} min</span>@endif</td>
                    <td><span class="badge bg-{{ $load->statusColor() }}">{{ __('app.load_status_' . $load->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">{{ __('app.no_loads') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $loads->links() }}</div>
@endsection
