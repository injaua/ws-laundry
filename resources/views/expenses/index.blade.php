@extends("layouts.admin")
@section("title", __('app.expenses'))
@section("page_title", __('app.expenses_list'))

@section("content")
@php($catsByKey = $categories->keyBy("key"))
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $expenses->total()]) }}</span>
    <a href="{{ route("expenses.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_expense') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("expenses.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search_expenses') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select" data-auto-submit>
                        <option value="">{{ __('app.all_categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->key }}" {{ request("category") == $category->key ? "selected" : "" }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("expenses.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.expense_title') }}</th>
                        <th>{{ __('app.category') }}</th>
                        <th>{{ __('app.amount') }}</th>
                        <th>{{ __('app.date') }}</th>
                        <th>{{ __('app.recorded_by') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-medium">{{ $expense->title }}
                            @if($expense->description)
                                <br><small class="text-muted">{{ Str::limit($expense->description, 60) }}</small>
                            @endif
                        </td>
                        <td>
                            @php($cat = $catsByKey->get($expense->category))
                            <span class="badge bg-{{ $cat->color ?? "secondary" }}">
                                {{ $cat->name ?? ucfirst($expense->category) }}
                            </span>
                            @if($cat?->is_fixed)
                                <span class="badge bg-dark">{{ __('app.fixed_badge') }}</span>
                                @if($expense->period_type)
                                    <span class="badge bg-light text-dark border">{{ $expense->period_type == "monthly" ? __('app.period_monthly') : __('app.period_once') }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="fw-semibold">{{ money($expense->amount) }}</td>
                        <td class="small">{{ $expense->expense_date->format(date_fmt()) }}</td>
                        <td class="small">{{ $expense->user?->name ?? "-" }}</td>
                        <td class="text-center">
                            @can("update", $expense)
                            <a href="{{ route("expenses.edit", $expense) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route("expenses.destroy", $expense) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_expense_confirm') }}">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @else
                            <span class="btn btn-sm btn-outline-secondary disabled" title="{{ __('app.record_of_another_user') }}"><i class="bi bi-lock"></i></span>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>
                            {{ __('app.no_expenses') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $expenses->links() }}</div>
@endsection
