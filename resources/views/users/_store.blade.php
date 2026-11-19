{{-- Admins choose the store of the account; owners always create staff in their own store. --}}
@if($stores->isNotEmpty())
<div class="mb-3">
    <label class="form-label" for="userStore">{{ __('app.store') }}</label>
    <select name="store_id" id="userStore" class="form-select @error("store_id") is-invalid @enderror">
        @foreach($stores as $store)
            <option value="{{ $store->id }}" @selected((string) old("store_id", $current ?? "") === (string) $store->id)>{{ $store->label() }}</option>
        @endforeach
    </select>
    <div class="invalid-feedback" data-feedback="store_id">@foreach($errors->get("store_id") as $message){{ $message }} @endforeach</div>
    <div class="form-text">{{ __('app.store_user_help') }}</div>
</div>
@endif
