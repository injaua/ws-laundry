@extends("layouts.admin")
@section("title", __('app.users'))
@section("page_title", __('app.users'))

@section("content")
<div class="page-title">
    <span>{{ __('app.total_count', ['count' => $users->total()]) }}</span>
    <a href="{{ route("users.create") }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> {{ __('app.new_user') }}
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form action="{{ route("users.index") }}" method="GET" class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="{{ __('app.search_users_placeholder') }}" value="{{ request("search") }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('app.filter') }} - {{ __('app.role') }}</option>
                        <option value="admin" @selected(request("role") == "admin")>{{ __('app.role_admin') }}</option>
                        <option value="owner" @selected(request("role") == "owner")>{{ __('app.role_owner') }}</option>
                        <option value="cashier" @selected(request("role") == "cashier")>{{ __('app.role_cashier') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">{{ __('app.search') }}</button>
                    <a href="{{ route("users.index") }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.name') }}</th>
                        <th>{{ __('app.email') }}</th>
                        <th>{{ __('app.role') }}</th>
                        <th>{{ __('app.is_active') }}</th>
                        <th>{{ __('app.created_at') }}</th>
                        <th class="text-center">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-medium">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if($user->role == "admin")
                                <span class="badge bg-danger"><i class="bi bi-shield-lock"></i> {{ __('app.role_admin') }}</span>
                            @elseif($user->role == "owner")
                                <span class="badge bg-primary"><i class="bi bi-person-badge"></i> {{ __('app.role_owner') }}</span>
                            @else
                                <span class="badge bg-info"><i class="bi bi-shop"></i> {{ __('app.role_cashier') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($user->is_active)
                                <span class="badge bg-success">{{ __('app.active') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('app.inactive') }}</span>
                            @endif
                        </td>
                        <td>{{ $user->created_at->format("d/m/Y") }}</td>
                        <td class="text-center">
                          @if($user->manageableBy(auth()->user()))
                            <a href="{{ route("users.edit", $user) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if(!$user->is(auth()->user()))
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" data-bs-boundary="window" aria-label="{{ __('app.actions') }}">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <form action="{{ route("users.toggle-active", $user) }}" method="POST">
                                            @csrf @method("PATCH")
                                            <button class="dropdown-item">
                                                <i class="bi bi-{{ $user->is_active ? "pause" : "play" }}-circle me-2"></i>{{ $user->is_active ? __('app.deactivate') : __('app.make_active') }}
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route("users.destroy", $user) }}" method="POST" onsubmit="return confirm('{{ __('app.delete_user_confirm') }}')">
                                            @csrf @method("DELETE")
                                            <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>{{ __('app.delete') }}</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                            @endif
                          @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            {{ __('app.no_users') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $users->links() }}
</div>
@endsection
