@extends("layouts.admin")
@section("title", __('app.production_report'))
@section("page_title", __('app.production_report'))
@section("page_subtitle", $from->format(date_fmt()) . ' – ' . $to->format(date_fmt()))

@php($num = fn ($n, $d = 1) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, $d, ".", ","), "0"), "."))
@php($o = $data['output'])
@php($p = $data['punctuality'])
@php($q = $data['quality'])
@php($st = $data['stage_times'])
@php($mc = $data['machines'])
@php($mat = $data['materials'])

@section("content")
<form method="GET" class="d-flex gap-2 flex-wrap mb-3">
    <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control mw-170px">
    <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control mw-170px">
    <button class="btn btn-primary"><i class="bi bi-funnel"></i> {{ __('app.filter') }}</button>
    <a href="{{ route('production.board') }}" class="btn btn-outline-secondary ms-auto"><i class="bi bi-kanban"></i> {{ __('app.production_board') }}</a>
</form>

<div class="row g-2 mb-3">
    @foreach([
        [__('app.kg_processed'), $num($o['kg']) . ' kg', __('app.per_day_avg', ['n' => $num($o['kg_per_day'])])],
        [__('app.pieces_processed'), $o['pieces'], $o['orders'] . ' ' . __('app.orders_lower')],
        [__('app.on_time_rate'), $p['rate'] === null ? '—' : $p['rate'] . '%', __('app.on_time_of', ['n' => $p['on_time'], 'total' => $p['measured']]) . ($p['rescheduled'] ? ' · ' . __('app.rescheduled_n', ['n' => $p['rescheduled']]) : '')],
        [__('app.complaints_per_100'), $num($q['complaints_per_100']), $q['complaints'] . ' / ' . $q['orders_received']],
        [__('app.rework_rate'), $q['rework_rate'] === null ? '—' : $q['rework_rate'] . '%', $q['reworks'] . ' ' . __('app.reworks_lower')],
        [__('app.material_cost_per_kg'), $mat['cost_per_kg'] === null ? '—' : money($mat['cost_per_kg'], 2), money($mat['total_cost'])],
    ] as [$label, $value, $hint])
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100"><div class="card-body py-2">
                <div class="small text-muted">{{ $label }}</div>
                <div class="fs-5 fw-bold">{{ $value }}</div>
                <div class="small text-muted">{{ $hint }}</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">{{ __('app.avg_time_per_stage') }}</div>
            <div class="card-body">
                @php($maxHours = max(1, collect($st['stages'])->max('avg_hours') ?? 1))
                @foreach($st['stages'] as $stage => $row)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span>{{ __('app.status_' . $stage) }} @if($st['bottleneck'] === $stage)<span class="badge bg-danger">{{ __('app.bottleneck') }}</span>@endif</span>
                            <span>{{ $row['avg_hours'] === null ? '—' : $num($row['avg_hours']) . ' h' }} <span class="text-muted">({{ $row['count'] }})</span></span>
                        </div>
                        <div class="progress h-6px"><div class="progress-bar {{ $stage === 'ready' ? 'bg-secondary' : '' }}" style="width: {{ $row['avg_hours'] ? min(100, $row['avg_hours'] / $maxHours * 100) : 0 }}%"></div></div>
                    </div>
                @endforeach
                <div class="small text-muted">{{ __('app.stage_time_help') }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">{{ __('app.output_per_day') }}</div>
            <div class="table-responsive mh-320px">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('app.date') }}</th><th class="text-end">{{ __('app.orders') }}</th><th class="text-end">kg</th><th class="text-end">{{ __('app.pieces_short') }}</th></tr></thead>
                    <tbody>
                    @forelse(array_reverse($o['days'], true) as $day => $row)
                        <tr><td>{{ \Illuminate\Support\Carbon::parse($day)->format('d/m (D)') }}</td><td class="text-end">{{ $row['orders'] }}</td><td class="text-end">{{ $num($row['kg']) }}</td><td class="text-end">{{ $row['pieces'] }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">{{ __('app.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">{{ __('app.quality') }}</div>
            <div class="card-body small">
                <div class="mb-2">{{ __('app.qc_checked_rate') }}: <strong>{{ $q['qc_rate'] === null ? '—' : $q['qc_rate'] . '%' }}</strong></div>
                @foreach(['by_category' => 'app.complaints_by_category', 'by_stage' => 'app.complaints_by_stage', 'by_machine' => 'app.complaints_by_machine', 'by_employee' => 'app.complaints_by_employee'] as $key => $title)
                    <div class="fw-semibold mt-2">{{ __($title) }}</div>
                    @forelse($q[$key] as $label => $count)
                        <span class="badge text-bg-light border me-1">{{ $key === 'by_category' ? __('app.complaint_category_' . $label) : ($key === 'by_stage' ? __('app.status_' . $label) : $label) }}: {{ $count }}</span>
                    @empty
                        <span class="text-muted">—</span>
                    @endforelse
                @endforeach
                @if($q['reworks_by_source'])
                    <div class="fw-semibold mt-2">{{ __('app.reworks_by_source') }}</div>
                    @foreach($q['reworks_by_source'] as $src => $count)
                        <span class="badge text-bg-light border me-1">{{ __('app.rework_source_' . $src) }}: {{ $count }}</span>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">{{ __('app.machines') }} · {{ $mc['total_loads'] }} {{ __('app.loads_lower') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>{{ __('app.machine') }}</th><th class="text-end">{{ __('app.loads') }}</th><th class="text-end">kg</th><th class="text-end">{{ __('app.utilization') }}</th><th class="text-end">min</th></tr></thead>
                    <tbody>
                    @forelse($mc['loads'] as $row)
                        <tr><td>{{ $row['machine']?->name }}</td><td class="text-end">{{ $row['loads'] }}</td><td class="text-end">{{ $num($row['kg']) }}</td>
                            <td class="text-end">{{ $row['utilization'] === null ? '—' : $row['utilization'] . '%' }}</td><td class="text-end">{{ $row['avg_minutes'] ?? '—' }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">{{ __('app.no_loads') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($mc['fleet']->isNotEmpty())
            <div class="card-body small border-top">
                <div class="fw-semibold mb-1">{{ __('app.cycles_and_service') }}</div>
                @foreach($mc['fleet'] as $f)
                    <div class="d-flex justify-content-between {{ $f['due'] ? 'text-danger fw-semibold' : '' }}">
                        <a href="{{ route('resources.equipments.show', $f['machine']) }}">{{ $f['machine']->name }}</a>
                        <span>{{ $f['cycles'] }} {{ __('app.cycles_lower') }}@if($f['interval']) · {{ $f['since_service'] }}/{{ $f['interval'] }}@endif</span>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">{{ __('app.operator_productivity') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr>
                        <th>{{ __('app.employee') }}</th><th class="text-end">{{ __('app.loads') }}</th><th class="text-end">{{ __('app.kg_loaded') }}</th>
                        @foreach(\App\Models\Order::PRODUCTION_STAGES as $s)<th class="text-end small">{{ __('app.status_' . $s) }}</th>@endforeach
                        <th class="text-end">{{ __('app.days_present') }}</th><th class="text-end">kg/{{ __('app.day') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($data['operators'] as $row)
                        <tr>
                            <td>{{ $row['employee']?->name ?? '—' }}</td>
                            <td class="text-end">{{ $row['loads'] }}</td>
                            <td class="text-end">{{ $num($row['kg_loaded']) }}</td>
                            @foreach(\App\Models\Order::PRODUCTION_STAGES as $s)
                                <td class="text-end small">{{ $row['finished'][$s]['orders'] ?? '—' }}</td>
                            @endforeach
                            <td class="text-end">{{ $row['days_present'] ?: '—' }}</td>
                            <td class="text-end">{{ $num($row['kg_per_day']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 5 + count(\App\Models\Order::PRODUCTION_STAGES) }}" class="text-center text-muted py-3">{{ __('app.no_operator_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">{{ __('app.operator_productivity_help') }}</div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">{{ __('app.theoretical_vs_physical') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>{{ __('app.item') }}</th><th class="text-end">{{ __('app.by_recipe') }}</th><th class="text-end">{{ __('app.count_adjustments') }}</th><th class="text-end">{{ __('app.variance') }}</th><th class="text-end">{{ currency_symbol() }}</th></tr></thead>
                    <tbody>
                    @forelse($mat['items'] as $row)
                        <tr>
                            <td>{{ $row['item']?->name }} <span class="text-muted small">{{ $row['item']?->unit }}</span></td>
                            <td class="text-end">{{ $num($row['consumed'], 2) }}</td>
                            <td class="text-end">{{ $num($row['adjusted'], 2) }}</td>
                            <td class="text-end {{ ($row['variance'] ?? 0) > 10 ? 'text-danger fw-semibold' : '' }}">{{ $row['variance'] === null ? '—' : $row['variance'] . '%' }}</td>
                            <td class="text-end">{{ num($row['cost']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">{{ __('app.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">{{ __('app.theoretical_vs_physical_help') }}</div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">{{ __('app.service_material_margin') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>{{ __('app.service') }}</th><th class="text-end">{{ __('app.price') }}</th><th class="text-end">{{ __('app.material_cost') }}</th><th class="text-end">{{ __('app.margin') }}</th></tr></thead>
                    <tbody>
                    @foreach($mat['services'] as $row)
                        <tr>
                            <td>{{ $row['service']->name }} <span class="small text-muted">/{{ $row['service']->type === 'by_weight' ? 'kg' : __('app.piece') }}</span></td>
                            <td class="text-end">{{ num($row['price']) }}</td>
                            <td class="text-end">{{ $row['has_recipe'] ? $num($row['unit_cost'], 2) : '—' }}</td>
                            <td class="text-end">{{ $row['has_recipe'] && $row['margin'] !== null ? $row['margin'] . '%' : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">{{ __('app.service_material_margin_help') }}</div>
        </div>
    </div>
</div>
@endsection
