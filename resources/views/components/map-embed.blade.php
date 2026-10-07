<div {{ $attributes->merge(['class' => 'map-embed']) }} data-map-embed style="--map-height: {{ (int) $height }}px">
    <div class="map-embed-fallback" role="status" data-map-fallback>
        <p class="fw-semibold mb-1" data-map-fallback-text>{{ $embedUrl !== '' ? 'Memuat peta…' : 'Peta tidak tersedia.' }}</p>
        @if ($address !== '')
            <p class="small mb-2">{{ $address }}</p>
        @endif
        @if ($directionsUrl !== '')
            <a class="btn btn-outline-primary btn-sm" href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>
        @endif
    </div>
    @if ($embedUrl !== '')
        <iframe src="{{ $embedUrl }}" title="{{ $title }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" data-map-frame></iframe>
    @endif
</div>
