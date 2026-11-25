{{-- Modals of the archive: upload, send to a colleague (new files / a document of the archive),
     folder (new / rename), category (new / edit). --}}
@php
    $uploadFailed = $errors->getBag("default")->any();
    $folderBag = $errors->getBag("folder");
    $categoryBag = $errors->getBag("category");
    $exchangeBag = $errors->getBag("exchange");
    $forwardBag = $errors->getBag("forward");
    $reopen = $uploadFailed ? "#uploadModal" : ($folderBag->any() ? "#folderModal" : ($categoryBag->any() ? "#categoryModal"
        : ($exchangeBag->any() ? "#exchangeModal" : ($forwardBag->any() ? "#forwardModal" : null))));
    $accept = collect(\App\Models\Document::extensions())->map(fn ($e) => "." . $e)->implode(",");
@endphp
@if($reopen)<div hidden data-doc-reopen="{{ $reopen }}"></div>@endif

{{-- Upload --}}
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form action="{{ route("documents-archive.store") }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalTitle"><i class="bi bi-cloud-arrow-up me-1"></i> {{ __('app.doc_upload_documents') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
            </div>
            <div class="modal-body">
                @if($uploadFailed)
                    <div class="alert alert-danger py-2 small">
                        @foreach($errors->getBag("default")->all() as $message)<div>{{ $message }}</div>@endforeach
                    </div>
                @endif
                <label class="doc-drop" id="docDrop">
                    <input type="file" name="files[]" multiple required accept="{{ $accept }}" class="visually-hidden" id="docFiles">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <strong>{{ __('app.doc_drop_here') }}</strong>
                    <span class="small text-muted">{{ __('app.doc_drop_hint', ['size' => \App\Http\Controllers\DocumentController::MAX_KB / 1024]) }}</span>
                </label>
                <ul class="doc-file-list" id="docFileList" hidden></ul>

                <div class="row g-3 mt-1">
                    <div class="col-md-8">
                        <label class="form-label" for="upTitle">{{ __('app.title') }}</label>
                        <input type="text" name="title" id="upTitle" class="form-control" maxlength="255" value="{{ old("title") }}">
                        <div class="form-text">{{ __('app.doc_title_hint') }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="upRef">{{ __('app.reference') }}</label>
                        <input type="text" name="reference" id="upRef" class="form-control" maxlength="100" value="{{ old("reference") }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="upFolder">{{ __('app.doc_folder') }}</label>
                        <select name="folder_id" id="upFolder" class="form-select">
                            <option value="">{{ __('app.doc_no_folder') }}</option>
                            @foreach($folders as $folder)
                                <option value="{{ $folder->id }}" @selected((int) old("folder_id", $filters["folder"]) === $folder->id)>{{ $folder->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="upCategory">{{ __('app.category') }}</label>
                        <select name="category_id" id="upCategory" class="form-select">
                            <option value="">{{ __('app.doc_uncategorized') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old("category_id", $filters["category"]) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="upDate">{{ __('app.doc_document_date') }}</label>
                        <input type="date" name="document_date" id="upDate" class="form-control" value="{{ old("document_date") }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="upExpires">{{ __('app.doc_expires_at') }}</label>
                        <input type="date" name="expires_at" id="upExpires" class="form-control" value="{{ old("expires_at") }}">
                        <div class="form-text">{{ __('app.doc_expires_hint') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="upTags">{{ __('app.doc_tags') }}</label>
                        <input type="text" name="tags" id="upTags" class="form-control" maxlength="500" value="{{ old("tags") }}" placeholder="{{ __('app.doc_tags_placeholder') }}">
                        @if($tags->isNotEmpty())
                            <div class="doc-tag-suggestions">
                                @foreach($tags->take(15) as $tag)
                                    <button type="button" class="badge doc-tag border-0" data-doc-add-tag="{{ $tag }}">#{{ $tag }}</button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="upDesc">{{ __('app.description') }}</label>
                        <textarea name="description" id="upDesc" rows="2" class="form-control" maxlength="2000">{{ old("description") }}</textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-arrow-up"></i> {{ __('app.doc_upload') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Send new files to colleagues --}}
@if($recipients->isNotEmpty())
<div class="modal fade" id="exchangeModal" tabindex="-1" aria-labelledby="exchangeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form action="{{ route("documents-archive.send") }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="exchangeModalTitle"><i class="bi bi-send me-1"></i> {{ __('app.doc_send_to_colleague') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
            </div>
            <div class="modal-body">
                @if($exchangeBag->has("files") || $exchangeBag->has("files.*"))
                    <div class="alert alert-danger py-2 small">
                        @foreach(collect($exchangeBag->getMessages())->filter(fn ($m, $k) => str_starts_with($k, "files"))->flatten() as $message)<div>{{ $message }}</div>@endforeach
                    </div>
                @endif
                <label class="doc-drop" id="exDrop">
                    <input type="file" name="files[]" multiple required accept="{{ $accept }}" class="visually-hidden" id="exFiles">
                    <i class="bi bi-paperclip"></i>
                    <strong>{{ __('app.doc_drop_here') }}</strong>
                    <span class="small text-muted">{{ __('app.doc_drop_hint', ['size' => \App\Http\Controllers\DocumentController::MAX_KB / 1024]) }}</span>
                </label>
                <ul class="doc-file-list" id="exFileList" hidden></ul>

                <div class="row g-3 mt-1">
                    <div class="col-12">
                        <label class="form-label" for="exTitle">{{ __('app.title') }}</label>
                        <input type="text" name="title" id="exTitle" class="form-control" maxlength="255" value="{{ $exchangeBag->any() ? old("title") : "" }}">
                        <div class="form-text">{{ __('app.doc_title_hint') }}</div>
                    </div>
                    <div class="col-12">
@include("documents-archive._recipients", ["bag" => $exchangeBag, "prefix" => "ex"])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="exFolder">{{ __('app.doc_folder') }}</label>
                        <select name="folder_id" id="exFolder" class="form-select">
                            <option value="">{{ __('app.doc_no_folder') }}</option>
                            @foreach($folders as $folder)
                                <option value="{{ $folder->id }}" @selected($exchangeBag->any() && (int) old("folder_id") === $folder->id)>{{ $folder->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="exCategory">{{ __('app.category') }}</label>
                        <select name="category_id" id="exCategory" class="form-select">
                            <option value="">{{ __('app.doc_uncategorized') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected($exchangeBag->any() && (int) old("category_id") === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="share_with_store" value="1" id="exShare" @checked($exchangeBag->any() && old("share_with_store"))>
                            <label class="form-check-label" for="exShare">{{ __('app.doc_share_with_store') }}</label>
                        </div>
                        <div class="form-text">{{ __('app.doc_share_with_store_hint') }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> {{ __('app.doc_send') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Send a document of the archive: the action is set from [data-doc-forward] --}}
<div class="modal fade" id="forwardModal" tabindex="-1" aria-labelledby="forwardModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ $forwardBag->any() ? old("_forward_url") : "#" }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="_forward_url" value="{{ $forwardBag->any() ? old("_forward_url") : "" }}">
            <input type="hidden" name="_forward_title" value="{{ $forwardBag->any() ? old("_forward_title") : "" }}">
            <div class="modal-header">
                <h5 class="modal-title" id="forwardModalTitle"><i class="bi bi-send me-1"></i> {{ __('app.doc_send_to_colleague') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="doc-forward-title mb-3"><i class="bi bi-file-earmark-text"></i> <strong class="js-forward-title">{{ $forwardBag->any() ? old("_forward_title") : "" }}</strong></div>
@include("documents-archive._recipients", ["bag" => $forwardBag, "prefix" => "fw"])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> {{ __('app.doc_send') }}</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Folder / category: the same modal creates, or renames when opened from [data-doc-edit]. --}}
@foreach([
    ["folderModal", $folderBag, route("documents-archive.folders.store"), \App\Models\DocumentFolder::COLORS, __('app.doc_new_folder'), __('app.doc_rename_folder'), "bi-folder-plus", __('app.doc_folder_name')],
    ["categoryModal", $categoryBag, route("documents-archive.categories.store"), \App\Models\DocumentCategory::COLORS, __('app.doc_new_category'), __('app.doc_edit_category'), "bi-tag", __('app.doc_category_name')],
] as [$id, $bag, $storeUrl, $colors, $newTitle, $editTitle, $icon, $nameLabel])
@php($editUrl = $bag->any() ? old("_edit_url") : null)
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ $editUrl ?: $storeUrl }}" method="POST" class="modal-content" data-store-url="{{ $storeUrl }}"
              data-new-title="{{ $newTitle }}" data-edit-title="{{ $editTitle }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" @disabled(!$editUrl)>
            <input type="hidden" name="_edit_url" value="{{ $editUrl }}" @disabled(!$editUrl)>
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Title"><i class="bi {{ $icon }} me-1"></i> <span class="js-title">{{ $editUrl ? $editTitle : $newTitle }}</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="{{ $id }}Name">{{ $nameLabel }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="{{ $id }}Name" class="form-control @if($bag->has("name")) is-invalid @endif" required maxlength="100" value="{{ $bag->any() ? old("name") : "" }}">
                    @if($bag->has("name"))<div class="invalid-feedback">{{ $bag->first("name") }}</div>@endif
                </div>
                <label class="form-label d-block">{{ __('app.color') }}</label>
                @include("documents-archive._color-picker", ["colors" => $colors, "selected" => $bag->any() ? old("color", $colors[0]) : $colors[0]])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endforeach
