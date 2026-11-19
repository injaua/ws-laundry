@extends("layouts.admin")
@section("title", __('app.expense_categories'))
@section("page_title", __('app.expense_categories'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $categories->count()]) }}</span>
    <a href="{{ route("expense-categories.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_category') }}
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>{{ __('app.category_name') }}</th>
                    <th>{{ __('app.category') }}</th>
                    <th>{{ __('app.expense_account') }}</th>
                    <th>{{ __('app.expenses') }}</th>
                    <th class="text-center">{{ __('app.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                <tr>
                    <td class="fw-medium">{{ $category->name }}</td>
                    <td>
                        <span class="badge bg-{{ $category->color }}">{{ $category->color }}</span>
                        @if($category->is_fixed)
                            <span class="badge bg-dark">{{ __('app.category_fixed') }}</span>
                            <small class="text-muted">{{ money($category->default_amount) }}</small>
                        @endif
                        @if($category->is_salary)
                            <span class="badge bg-success">{{ __('app.category_salary') }}</span>
                        @endif
                        @if($category->is_vat_applicable)
                            <span class="badge bg-warning text-dark">{{ __('app.vat_applicable') }}</span>
                        @endif
                    </td>
                    <td>
                        @if($category->account)
                            <span class="text-muted">{{ $category->account->code }}</span> {{ $category->account->name }}
                        @else
                            <span class="text-danger">—</span>
                        @endif
                    </td>
                    <td>{{ $category->expenseCount() }}</td>
                    <td class="text-center">
                        <a href="{{ route("expense-categories.edit", $category) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route("expense-categories.destroy", $category) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_category_confirm') }}">
                            @csrf @method("DELETE")
                            <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}" {{ $category->expenseCount() > 0 ? "disabled" : "" }}>
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">{{ __('app.no_categories') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
