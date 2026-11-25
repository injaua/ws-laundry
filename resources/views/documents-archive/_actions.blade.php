{{-- Actions on one document: favourite, open / download, send to a colleague, edit, delete. --}}
<form action="{{ route("documents-archive.favorite", $document) }}" method="POST" class="d-inline js-doc-favorite" data-no-lock>
    @csrf
    <button type="submit" class="btn btn-sm doc-fav {{ $document->is_favorite ? "on" : "" }}" aria-pressed="{{ $document->is_favorite ? "true" : "false" }}"
            title="{{ __('app.doc_toggle_favorite') }}" aria-label="{{ __('app.doc_toggle_favorite') }}">
        <i class="bi {{ $document->is_favorite ? "bi-star-fill" : "bi-star" }}"></i>
    </button>
</form>
@if($document->isPdf())
<a href="{{ route("documents-archive.preview", $document) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="{{ __('app.doc_open') }}" aria-label="{{ __('app.doc_open') }}">
    <i class="bi bi-eye"></i>
</a>
@endif
<a href="{{ route("documents-archive.download", $document) }}" download data-no-nav class="btn btn-sm btn-outline-secondary" title="{{ __('app.download') }}" aria-label="{{ __('app.download') }}">
    <i class="bi bi-download"></i>
</a>
@if($recipients->isNotEmpty())
<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#forwardModal"
        data-doc-forward="{{ route("documents-archive.forward", $document) }}" data-title="{{ $document->title }}"
        title="{{ __('app.doc_send_to_colleague') }}" aria-label="{{ __('app.doc_send_to_colleague') }}">
    <i class="bi bi-send"></i>
</button>
@endif
@can("update", $document)
<a href="{{ route("documents-archive.edit", $document) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.edit') }}" aria-label="{{ __('app.edit') }}">
    <i class="bi bi-pencil"></i>
</a>
<form action="{{ route("documents-archive.destroy", $document) }}" method="POST" class="d-inline" data-confirm="{{ __('app.doc_delete_confirm', ['title' => $document->title]) }}">
    @csrf @method("DELETE")
    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}" aria-label="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
</form>
@else
<a href="{{ route("documents-archive.edit", $document) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.doc_details') }}" aria-label="{{ __('app.doc_details') }}">
    <i class="bi bi-info-circle"></i>
</a>
@endcan
