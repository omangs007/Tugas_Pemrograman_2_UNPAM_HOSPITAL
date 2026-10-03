@props(['title', 'message' => null, 'icon' => 'bi-inbox'])
<div {{ $attributes->merge(['class' => 'empty-state', 'role' => 'status']) }}>
    <i class="bi {{ $icon }} empty-state-icon d-block mb-2" aria-hidden="true"></i>
    <p class="h5 fw-semibold mb-1">{{ $title }}</p>
    @if ($message)
        <p class="mb-3">{{ $message }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="d-flex flex-wrap justify-content-center gap-2">{{ $slot }}</div>
    @endif
</div>
