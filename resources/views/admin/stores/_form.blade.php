<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">{{ __('app.code') }} <span class="text-danger">*</span></label>
        <input type="text" name="code" value="{{ old("code", $store->code) }}" class="form-control text-uppercase @error("code") is-invalid @enderror" maxlength="20" required>
        @error("code")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-9">
        <label class="form-label">{{ __('app.name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old("name", $store->name) }}" class="form-control @error("name") is-invalid @enderror" maxlength="120" required>
        @error("name")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label">{{ __('app.address') }}</label>
        <input type="text" name="address" value="{{ old("address", $store->address) }}" class="form-control @error("address") is-invalid @enderror" maxlength="255">
        @error("address")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.phone') }}</label>
        <input type="text" name="phone" value="{{ old("phone", $store->phone) }}" class="form-control @error("phone") is-invalid @enderror" maxlength="30">
        @error("phone")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.email') }}</label>
        <input type="email" name="email" value="{{ old("email", $store->email) }}" class="form-control @error("email") is-invalid @enderror" maxlength="120">
        @error("email")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">NUIT</label>
        <input type="text" name="nuit" value="{{ old("nuit", $store->nuit) }}" class="form-control @error("nuit") is-invalid @enderror" maxlength="20">
        @error("nuit")<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="storeActive" @checked(old("is_active", $store->is_active))>
            <label class="form-check-label" for="storeActive">{{ __('app.active') }}</label>
        </div>
    </div>
</div>
