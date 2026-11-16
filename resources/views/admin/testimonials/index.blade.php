@extends("layouts.admin")
@section("title", __('app.testimonials'))
@section("page_title", __('app.testimonials'))

@section("content")
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-star me-1"></i> {{ __('app.testimonials') }}</span>
        <a href="{{ route("admin.testimonials.create") }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> {{ __('app.save') }}
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>{{ __('app.customer') }}</th>
                        <th>{{ __('app.description') }}</th>
                        <th>{{ __('app.rating') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.sort_order') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($testimonials as $t)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="fw-medium">{{ $t->customer_name }}</td>
                            <td class="small text-muted" style="max-width:300px">
                                {{ Str::limit($t->content, 80) }}
                            </td>
                            <td>
                                @for($i=1;$i<=5;$i++)
                                    <i class="bi bi-star{{ $i<=$t->rating ? "-fill text-warning" : "" }}"></i>
                                @endfor
                            </td>
                            <td>
                                @if($t->is_active)
                                    <span class="badge bg-success">{{ __('app.active') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('app.no') }}</span>
                                @endif
                            </td>
                            <td>{{ $t->sort_order }}</td>
                            <td class="text-end">
                                <a href="{{ route("admin.testimonials.edit", $t) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route("admin.testimonials.destroy", $t) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('{{ __('app.confirm_delete') }}')">
                                    @csrf @method("DELETE")
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">{{ __('app.no_testimonials') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
