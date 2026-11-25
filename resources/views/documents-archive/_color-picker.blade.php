{{-- Colour swatches (Bootstrap contextual colours). --}}
<div class="doc-colors" role="radiogroup" aria-label="{{ __('app.color') }}">
    @foreach($colors as $color)
        <label class="doc-color" title="{{ $color }}">
            <input type="radio" name="color" value="{{ $color }}" @checked($selected === $color)>
            <span class="bg-{{ $color }}"></span>
        </label>
    @endforeach
</div>
