@extends("layouts.admin")
@section("title", __('app.gallery'))
@section("page_title", __('app.gallery'))

@section("content")
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-images me-1"></i> {{ __('app.gallery') }}</span>
        <a href="{{ route("admin.galleries.create") }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> {{ __('app.upload') }}
        </a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @forelse($galleries as $g)
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100">
                        <img src="{{ Storage::url($g->photo) }}" class="card-img-top h-160px img-cover">
                        <div class="card-body p-2">
                            <p class="small mb-1 text-truncate">{{ $g->caption ?? "—" }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-{{ $g->is_active ? "success" : "secondary" }} small">
                                    {{ $g->is_active ? __('app.active') : __('app.no') }}
                                </span>
                                <div>
                                    <a href="{{ route("admin.galleries.edit", $g) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route("admin.galleries.destroy", $g) }}" method="POST" class="d-inline"
                                        data-confirm="{{ __('app.confirm_delete') }}">
                                        @csrf @method("DELETE")
                                        <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted py-4">{{ __('app.no_photos') }}</div>
            @endforelse
        </div>
    </div>
    @if($galleries->hasPages())
        <div class="card-footer">{{ $galleries->links() }}</div>
    @endif
</div>
@endsection
