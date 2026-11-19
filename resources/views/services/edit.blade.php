@extends("layouts.admin")
@section("title", __('app.edit_service'))
@section("page_title", __('app.edit_service'))

@section("content")
<div class="row justify-content-center g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-gear me-1"></i> {{ __('app.edit_service') }}</div>
            <div class="card-body">
                <form action="{{ route("services.update", $service) }}" method="POST" enctype="multipart/form-data">
                    @csrf @method("PUT")
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.service_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error("name") is-invalid @enderror" value="{{ old("name", $service->name) }}" required>
                        @error("name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.service_type') }} <span class="text-danger">*</span></label>
                        <select name="type" class="form-select @error("type") is-invalid @enderror" required>
                            <option value="by_weight" {{ old("type", $service->type) == "by_weight" ? "selected" : "" }}>{{ __('app.per_kg') }}</option>
                            <option value="by_piece" {{ old("type", $service->type) == "by_piece" ? "selected" : "" }}>{{ __('app.per_piece') }}</option>
                        </select>
                        @error("type") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.price') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="price" class="form-control @error("price") is-invalid @enderror" value="{{ old("price", $service->price) }}" required min="0" max="10000">
                            <span class="input-group-text">{{ currency_symbol() }}</span>
                            @error("price") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.estimated_work') }}</label>
                        <div class="input-group">
                            <input type="number" name="estimated_days" class="form-control @error("estimated_days") is-invalid @enderror" value="{{ old("estimated_days", $service->estimated_days) }}" min="1">
                            <span class="input-group-text">{{ __('app.days') }}</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.service_photo') }}</label>
                        @if($service->photo)
                            <div class="mb-2">
                                <img id="photoPreview" src="{{ Storage::url($service->photo) }}" alt="{{ $service->name }}" class="rounded border mh-160px">
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="remove_photo" value="1" class="form-check-input" id="removePhoto">
                                <label class="form-check-label" for="removePhoto">{{ __('app.remove_photo') }}</label>
                            </div>
                        @else
                            <div class="mb-2 d-none" id="photoPreviewWrap">
                                <img id="photoPreview" class="rounded border mh-160px" alt="">
                            </div>
                        @endif
                        <input type="file" name="photo" data-preview="#photoPreview" data-preview-uncheck="#removePhoto" class="form-control @error("photo") is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">{{ __('app.photo_optional_hint') }}</div>
                        @error("photo") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('app.update') }}</button>
                        <a href="{{ route("services.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-droplet-half me-1"></i> {{ __('app.consumption_recipe') }}</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('app.item') }}</th>
                            <th class="text-end">{{ __('app.quantity') }}</th>
                            <th>{{ __('app.recipe_basis') }}</th>
                            <th>{{ __('app.recipe_stage') }}</th>
                            <th class="text-center">{{ __('app.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recipes as $recipe)
                        <tr>
                            <td class="small">{{ $recipe->item->name }} <span class="text-muted">({{ __('app.unit_' . $recipe->item->unit) }})</span></td>
                            <td class="text-end small">{{ qty((float) $recipe->quantity) }}</td>
                            <td class="small">{{ __('app.recipe_basis_' . $recipe->basis) }}</td>
                            <td class="small"><span class="badge bg-{{ $recipe->stage === "washing" ? "info" : "secondary" }}">{{ __('app.status_' . $recipe->stage) }}</span></td>
                            <td class="text-center">
                                <form action="{{ route("services.recipes.destroy", [$service, $recipe]) }}" method="POST" class="d-inline" data-confirm="{{ __('app.delete_confirm') }}">
                                    @csrf @method("DELETE")
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-3 text-muted small">{{ __('app.no_recipes') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <form action="{{ route("services.recipes.store", $service) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12">
                        <label class="form-label small">{{ __('app.item') }} <span class="text-danger">*</span></label>
                        <select name="inventory_item_id" class="form-select form-select-sm @error('inventory_item_id') is-invalid @enderror" required>
                            <option value="">—</option>
                            @foreach($recipeItems as $recipeItem)
                                <option value="{{ $recipeItem->id }}" {{ old("inventory_item_id") == $recipeItem->id ? "selected" : "" }}>{{ $recipeItem->name }} ({{ __('app.unit_' . $recipeItem->unit) }})</option>
                            @endforeach
                        </select>
                        @error('inventory_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-4">
                        <label class="form-label small">{{ __('app.quantity') }} <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control form-control-sm @error('quantity') is-invalid @enderror" min="0.001" step="0.001" value="{{ old("quantity", 0.01) }}" required>
                        @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-4">
                        <label class="form-label small">{{ __('app.recipe_basis') }}</label>
                        <select name="basis" class="form-select form-select-sm" required>
                            @foreach(\App\Models\ServiceRecipe::BASES as $bs)
                                <option value="{{ $bs }}" {{ old("basis", "per_kg") == $bs ? "selected" : "" }}>{{ __('app.recipe_basis_' . $bs) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">{{ __('app.recipe_stage') }} <span class="text-danger">*</span></label>
                        <select name="stage" class="form-select form-select-sm @error('stage') is-invalid @enderror" required>
                            @foreach(\App\Models\ServiceRecipe::STAGES as $st)
                                <option value="{{ $st }}" {{ old("stage", "washing") == $st ? "selected" : "" }}>{{ __('app.status_' . $st) }}</option>
                            @endforeach
                        </select>
                        @error('stage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> {{ __('app.add') }}</button>
                    </div>
                </form>
                <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ __('app.recipe_hint') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

