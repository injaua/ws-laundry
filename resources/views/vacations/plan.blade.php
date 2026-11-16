@extends("layouts.admin")
@section("title", __('app.vacation_plan'))
@section("page_title", __('app.vacation_plan'))

@section("content")
@php
    $months = __('app.months_short');
@endphp
<div class="page-title">
    <span>{{ $year }}</span>
    <a href="{{ route("vacations.index") }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-list"></i> {{ __('app.vacations') }}
    </a>
</div>

<div class="card mb-3">
    <div class="card-body p-3">
        <form action="{{ route("vacations.plan") }}" method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <select name="year" class="form-select" onchange="this.form.submit()">
                    @for($y = now()->year + 1; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? "selected" : "" }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
            </div>
            <div class="col-md-auto ms-md-auto">
                <small class="text-muted me-2"><span class="d-inline-block rounded-pill bg-success" style="width:.9rem;height:.9rem"></span> {{ __('app.approved') }}</small>
                <small class="text-muted"><span class="d-inline-block rounded-pill bg-warning" style="width:.9rem;height:.9rem"></span> {{ __('app.pending') }}</small>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-calendar3 me-1"></i> {{ __('app.vacation_plan') }} — {{ $year }}</div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="sticky-top start-0 bg-white">{{ __('app.employee') }}</th>
                    @for($m = 1; $m <= 12; $m++)
                        <th class="text-center small">{{ $months[$m - 1] }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                <tr>
                    <td class="fw-medium sticky-top start-0 bg-white">
                        <a href="{{ route("employees.show", $employee) }}" class="text-decoration-none">{{ $employee->name }}</a>
                    </td>
                    @for($m = 1; $m <= 12; $m++)
                        @php
                            $cell = $plan[$employee->id][$m] ?? null;
                            $approved = $cell["approved"] ?? 0;
                            $pending = $cell["pending"] ?? 0;
                            $class = $approved ? "table-success" : ($pending ? "table-warning" : "");
                            $label = $approved
                                ? $approved."d".($pending ? " +".$pending."d" : "")
                                : ($pending ? $pending."d" : "");
                        @endphp
                        <td class="text-center small {{ $class }}">{{ $label }}</td>
                    @endfor
                </tr>
                @empty
                <tr><td colspan="13" class="text-center py-4 text-muted">{{ __('app.no_employees') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
