@php
    $cover = $clinic->firstMedia('cover');
@endphp
<article class="card h-100 service-card">
    <x-image type="clinic" :media="$cover" :alt="$cover?->alt_text ?: 'Foto '.$clinic->name" variant="thumb" ratio="4x3" />
    <div class="card-body d-flex flex-column">
        <h3 class="card-title h5 mb-1">
            <a class="stretched-link" href="{{ route('clinics.show', $clinic) }}">{{ $clinic->name }}</a>
        </h3>
        <p class="small text-body-secondary mb-2"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $clinic->location }}</p>
        <p class="card-text mb-3">{{ $clinic->short_description }}</p>
        @if ($clinic->is_bookable)
            <p class="mt-auto mb-0"><span class="badge text-bg-success"><i class="bi bi-calendar-check" aria-hidden="true"></i> Dapat didaftar online</span></p>
        @endif
    </div>
</article>
