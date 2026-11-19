@php($supplier = $supplier ?? null)
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $supplier?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">{{ __('app.contact_person') }}</label>
        <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $supplier?->contact_person) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.phone') }}</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier?->phone) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.email') }}</label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $supplier?->email) }}">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.nit') }}</label>
        <input type="text" name="nit" class="form-control" value="{{ old('nit', $supplier?->nit) }}">
    </div>
    <div class="col-md-8">
        <label class="form-label">{{ __('app.address') }}</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $supplier?->address) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.city') }}</label>
        <input type="text" name="city" class="form-control" value="{{ old('city', $supplier?->city) }}">
    </div>
    <div class="col-12">
        <label class="form-label">{{ __('app.notes') }}</label>
        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $supplier?->notes) }}</textarea>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old("is_active", $supplier?->is_active ?? true) ? "checked" : "" }}>
            <label class="form-check-label small" for="is_active">{{ __('app.active') }}</label>
        </div>
    </div>
</div>
