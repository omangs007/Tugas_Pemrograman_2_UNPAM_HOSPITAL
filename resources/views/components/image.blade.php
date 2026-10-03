@if ($ratio)<div class="image-frame ratio ratio-{{ $ratio }}">@endif
<img {{ $attributes->merge(['class' => $ratio ? 'object-fit-cover' : 'img-fluid']) }} src="{{ $imageSrc }}" alt="{{ $altText }}"@if ($decorative) role="presentation"@endif @if ($fallbackSrc) data-fallback="{{ $fallbackSrc }}"@endif @if ($lazy) loading="lazy"@endif decoding="async"@if ($imageWidth && $imageHeight) width="{{ $imageWidth }}" height="{{ $imageHeight }}"@endif>
@if ($ratio)</div>@endif
