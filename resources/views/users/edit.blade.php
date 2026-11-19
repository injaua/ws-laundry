@extends("layouts.admin")
@section("title", __('app.edit_user'))
@section("page_title", __('app.edit_user'))

@section("content")
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route("users.update", $user) }}" method="POST" id="userForm" novalidate>
                    @csrf @method("PATCH")

                    @include("users._fields", ["current" => $user->store_id])

                    <div class="form-check form-switch mb-3">
                        {{-- One's own account stays active (the switch is locked and not sent). --}}
                        <input type="hidden" name="is_active" value="{{ $user->is(auth()->user()) ? 1 : 0 }}">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                            @checked(old("is_active", $user->is_active))
                            @disabled($user->is(auth()->user()))>
                        <label class="form-check-label" for="is_active">{{ __('app.active') }}</label>
                        @if($user->is(auth()->user()))
                            <div class="form-text">{{ __('app.cannot_deactivate_self') }}</div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route("users.index") }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                        <button type="submit" class="btn btn-primary" data-submit><i class="bi bi-check-lg"></i> {{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
