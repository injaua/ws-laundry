@extends("layouts.admin")
@section("title", __('app.chart_of_accounts'))
@section("page_title", __('app.chart_of_accounts'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $accounts->count()]) }}</span>
    <a href="{{ route("accounting.accounts.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_account') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('app.account_code') }}</th>
                        <th>{{ __('app.account_name') }}</th>
                        <th>{{ __('app.account_type') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                    <tr class="{{ $account->is_group ? "table-light fw-semibold" : "" }}">
                        <td class="{{ $account->is_group ? "" : "ps-4" }}">{{ $account->code }}</td>
                        <td>
                            {{ $account->name }}
                            @if(!$account->is_active)
                                <span class="badge bg-secondary ms-1">{{ __('app.inactive') }}</span>
                            @endif
                        </td>
                        <td><span class="badge bg-{{ ["asset" => "primary", "liability" => "warning", "equity" => "dark", "revenue" => "success", "expense" => "danger"][$account->type] }}">
                            {{ __('app.account_type_' . $account->type) }}
                        </span></td>
                        <td class="text-center">
                            <a href="{{ route("accounting.accounts.edit", $account) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route("accounting.accounts.destroy", $account) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('app.delete_account_confirm') }}')">
                                @csrf @method("DELETE")
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}" {{ $account->lines()->exists() || $account->children()->exists() ? "disabled" : "" }}>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
