@php
    $cover = $facility->firstMedia('cover');
    $level = $headingLevel ?? 3;
@endphp
<article class="card h-100 facility-card">
    <x-image type="facility" :media="$cover" :alt="$cover?->alt_text ?: $facility->name" variant="thumb" ratio="4x3" />
    <div class="card-body">
        <h{{ $level }} class="card-title h5 mb-1">
            <a class="stretched-link" href="{{ route('facilities.show', $facility) }}">{{ $facility->name }}</a>
        </h{{ $level }}>
        @if (filled($facility->short_description))
            <p class="card-text mb-0">{{ $facility->short_description }}</p>
        @endif
    </div>
</article>
