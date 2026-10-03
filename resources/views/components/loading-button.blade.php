@props(['type' => 'submit', 'variant' => 'primary', 'loadingText' => 'Memproses…', 'icon' => null])
<button type="{{ $type }}" {{ $attributes->merge(['class' => 'btn btn-'.$variant]) }} data-loading-button data-loading-text="{{ $loadingText }}">
    <span class="spinner-border spinner-border-sm d-none" role="status" data-loading-spinner><span class="visually-hidden">Memproses…</span></span>
    @if ($icon)<i class="bi {{ $icon }}" aria-hidden="true" data-loading-icon></i>@endif
    <span data-loading-label>{{ $slot }}</span>
</button>
