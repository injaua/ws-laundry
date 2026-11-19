@extends("layouts.admin")
@section("title", __('app.promotions'))
@section("page_title", __('app.promotions'))
@section("page_subtitle", __('app.promotions_subtitle'))

@php($statusColor = ["active" => "success", "scheduled" => "info", "expired" => "secondary", "inactive" => "dark"])

@section("content")
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <p class="text-muted small mb-0 mw-640px">{{ __('app.promotions_rules_hint') }}</p>
    <a href="{{ route("promotions.create") }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('app.new_promotion') }}</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.name') }}</th>
                        <th>{{ __('app.discount') }}</th>
                        <th>{{ __('app.applies_to') }}</th>
                        <th>{{ __('app.period') }}</th>
                        <th class="text-end">{{ __('app.promotion_usage') }}</th>
                        <th class="text-center">{{ __('app.status') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promotions as $promotion)
                    @php($status = $promotion->status())
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $promotion->name }}</div>
                            @if($promotion->description)<div class="small text-muted">{{ $promotion->description }}</div>@endif
                        </td>
                        <td class="fw-semibold text-danger">
                            {{ $promotion->valueLabel() }}
                            @if($promotion->discount_type === "fixed")<div class="small text-muted fw-normal">{{ __('app.fixed_discount_per_unit') }}</div>@endif
                        </td>
                        <td class="small">
                            @if($promotion->applies_to === "all")
                                {{ __('app.promotion_scope_all') }}
                            @else
                                {{ $promotion->items->map(fn ($i) => $i->service?->name ?? $i->package?->name)->filter()->join(", ") }}
                            @endif
                        </td>
                        <td class="small">
                            {{ $promotion->starts_on->format(date_fmt()) }} – {{ $promotion->ends_on?->format(date_fmt()) ?? __('app.no_end_date') }}
                            @if($promotion->weekdays)
                                <div class="text-muted">{{ collect($promotion->weekdays)->map(fn ($d) => __('app.weekday_short_' . $d))->join(", ") }}</div>
                            @endif
                        </td>
                        <td class="text-end small">
                            @if($u = $usage->get($promotion->id))
                                {{ __('app.promotion_usage_orders', ["count" => $u["orders"]]) }}
                                <div class="text-muted">−{{ money($u["discount"]) }}</div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-center"><span class="badge bg-{{ $statusColor[$status] }}">{{ __('app.promotion_status_' . $status) }}</span></td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route("promotions.edit", $promotion) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route("promotions.destroy", $promotion) }}" method="POST" class="d-inline" data-confirm="{{ __('app.confirm_delete') }}">
                                @csrf @method("DELETE")
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('app.no_promotions') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
