@extends("layouts.admin")
@section("title", $document->title)
@section("page_title", __('app.doc_archive'))

@push("styles")
    @vite("resources/css/pages/documents.css")
@endpush

@section("content")
@php($canEdit = auth()->user()->can("update", $document))
@php($isUploader = (int) $document->uploaded_by === (int) auth()->id())
<div class="mb-3">
    <a href="{{ route("documents-archive.index", array_filter(["folder" => $document->folder_id])) }}" class="text-decoration-none small">
        <i class="bi bi-arrow-left"></i> {{ __('app.doc_back_to_archive') }}
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card doc-panel">
            <div class="card-body text-center">
                <span class="doc-file-icon lg tint-{{ $document->color() }} mx-auto"><i class="bi {{ $document->icon() }}"></i></span>
                <h5 class="mt-3 mb-1 text-break">{{ $document->title }}</h5>
                <div class="small text-muted text-break">{{ $document->original_name }}</div>
                <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                    @if($document->isPdf())
                        <a href="{{ route("documents-archive.preview", $document) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye"></i> {{ __('app.doc_open') }}</a>
                    @endif
                    <a href="{{ route("documents-archive.download", $document) }}" download data-no-nav class="btn btn-primary btn-sm"><i class="bi bi-download"></i> {{ __('app.download') }}</a>
                </div>
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('app.doc_type') }}</span><span>{{ __('app.doc_type_' . $document->type()) }} ({{ strtoupper($document->extension) }})</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('app.doc_size') }}</span><span>{{ \App\Models\Document::formatSize($document->size) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('app.doc_uploaded_by') }}</span><span>{{ $document->uploader?->name ?? "—" }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('app.doc_uploaded_at') }}</span><span>{{ $document->created_at->format(date_fmt() . " H:i") }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('app.doc_expires_at') }}</span><span>@include("documents-archive._expiry", ["document" => $document])</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('app.doc_visibility') }}</span>
                    <span>@if($document->is_private)<i class="bi bi-lock-fill"></i> {{ __('app.doc_private') }}@else<i class="bi bi-people"></i> {{ __('app.doc_whole_store') }}@endif</span>
                </li>
            </ul>
        </div>

        @if($shares->isNotEmpty())
        <div class="card doc-panel mt-3">
            <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i> {{ __('app.doc_exchange_history') }}</div>
            <ul class="list-group list-group-flush small">
                @foreach($shares as $share)
                <li class="list-group-item">
                    <div class="d-flex justify-content-between gap-2">
                        <span class="min-w-0 text-truncate">{{ $share->sender?->name ?? "—" }} <i class="bi bi-arrow-right text-muted"></i> {{ $share->recipient?->name ?? "—" }}</span>
                        <span class="text-nowrap {{ $share->isRead() ? "text-success" : "text-muted" }}" title="{{ $share->read_at?->format(date_fmt() . " H:i") }}">
                            <i class="bi {{ $share->isRead() ? "bi-check2-all" : "bi-check2" }}"></i> {{ $share->isRead() ? __('app.doc_read') : __('app.doc_not_read') }}
                        </span>
                    </div>
                    <div class="text-muted">{{ $share->created_at->format(date_fmt() . " H:i") }}</div>
                    @if($share->message)<div class="mt-1 doc-share-message">{{ $share->message }}</div>@endif
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    <div class="col-lg-8">
        <div class="card doc-panel">
            <div class="card-header"><i class="bi bi-{{ $canEdit ? "pencil" : "info-circle" }} me-1"></i> {{ $canEdit ? __('app.doc_edit') : __('app.doc_details') }}</div>
            <div class="card-body">
                @unless($canEdit)
                    <div class="alert alert-light border small"><i class="bi bi-lock"></i> {{ __('app.record_of_another_user') }}</div>
                @endunless
                <form action="{{ route("documents-archive.update", $document) }}" method="POST" enctype="multipart/form-data">
                    @csrf @method("PUT")
                    <fieldset @disabled(!$canEdit)>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="title">{{ __('app.title') }} <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control @error("title") is-invalid @enderror" required maxlength="255" value="{{ old("title", $document->title) }}">
                            @error("title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="reference">{{ __('app.reference') }}</label>
                            <input type="text" name="reference" id="reference" class="form-control @error("reference") is-invalid @enderror" maxlength="100" value="{{ old("reference", $document->reference) }}">
                            @error("reference")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="folder_id">{{ __('app.doc_folder') }}</label>
                            <select name="folder_id" id="folder_id" class="form-select @error("folder_id") is-invalid @enderror">
                                <option value="">{{ __('app.doc_no_folder') }}</option>
                                @foreach($folders as $folder)
                                    <option value="{{ $folder->id }}" @selected((int) old("folder_id", $document->folder_id) === $folder->id)>{{ $folder->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="category_id">{{ __('app.category') }}</label>
                            <select name="category_id" id="category_id" class="form-select @error("category_id") is-invalid @enderror">
                                <option value="">{{ __('app.doc_uncategorized') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((int) old("category_id", $document->category_id) === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="document_date">{{ __('app.doc_document_date') }}</label>
                            <input type="date" name="document_date" id="document_date" class="form-control @error("document_date") is-invalid @enderror" value="{{ old("document_date", $document->document_date?->format("Y-m-d")) }}">
                            @error("document_date")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="expires_at">{{ __('app.doc_expires_at') }}</label>
                            <input type="date" name="expires_at" id="expires_at" class="form-control @error("expires_at") is-invalid @enderror" value="{{ old("expires_at", $document->expires_at?->format("Y-m-d")) }}">
                            @error("expires_at")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="tags">{{ __('app.doc_tags') }}</label>
                            <input type="text" name="tags" id="tags" class="form-control @error("tags") is-invalid @enderror" maxlength="500" value="{{ old("tags", implode(", ", $document->tags ?? [])) }}" placeholder="{{ __('app.doc_tags_placeholder') }}">
                            @error("tags")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">{{ __('app.description') }}</label>
                            <textarea name="description" id="description" rows="3" class="form-control @error("description") is-invalid @enderror" maxlength="2000">{{ old("description", $document->description) }}</textarea>
                        </div>
                        @if($canEdit && $isUploader)
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_private" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_private" value="1" id="is_private" @checked(old("is_private", $document->is_private))>
                                <label class="form-check-label" for="is_private">{{ __('app.doc_private_switch') }}</label>
                            </div>
                            <div class="form-text">{{ __('app.doc_private_switch_hint') }}</div>
                        </div>
                        @endif
                        @if($canEdit)
                        <div class="col-12">
                            <label class="form-label" for="file">{{ __('app.doc_replace_file') }}</label>
                            <input type="file" name="file" id="file" class="form-control @error("file") is-invalid @enderror" accept="{{ collect(\App\Models\Document::extensions())->map(fn ($e) => "." . $e)->implode(",") }}">
                            @error("file")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('app.doc_replace_file_hint') }}</div>
                        </div>
                        @endif
                    </div>
                    </fieldset>
                    @if($canEdit)
                    <div class="d-flex justify-content-between mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
                    </div>
                    @endif
                </form>
                @if($canEdit)
                <form action="{{ route("documents-archive.destroy", $document) }}" method="POST" class="mt-3 text-end" data-confirm="{{ __('app.doc_delete_confirm', ['title' => $document->title]) }}">
                    @csrf @method("DELETE")
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> {{ __('app.delete') }}</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
