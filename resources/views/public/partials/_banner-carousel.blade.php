@php
    $count = $banners->count();
@endphp
<div id="banner-carousel" class="carousel slide banner-carousel" @if ($count > 1) data-banner-carousel @endif role="region" aria-roledescription="carousel" aria-label="Banner">
    <div class="carousel-inner" data-banner-live aria-live="off">
        @foreach ($banners as $banner)
            @php
                $image = $banner->firstMedia('image');
                $buttonUrl = $banner->hasUsableButton() ? $banner->button_url : null;
                $external = $buttonUrl !== null && str_starts_with($buttonUrl, 'https://');
            @endphp
            <div @class(['carousel-item', 'active' => $loop->first]) role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} dari {{ $count }}">
                <div class="banner-slide-media">
                    <x-image type="banner" :media="$image" :alt="$image?->alt_text ?: $banner->title" :lazy="! $loop->first" />
                </div>
                <div class="banner-caption">
                    <p class="h2 mb-1">{{ $banner->title }}</p>
                    @if (filled($banner->subtitle))
                        <p class="mb-0">{{ $banner->subtitle }}</p>
                    @endif
                    @if ($buttonUrl !== null)
                        <a class="btn btn-light mt-3" href="{{ $buttonUrl }}"@if ($external) rel="noopener noreferrer"@endif>{{ $banner->button_label }}</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($count > 1)
        <div class="banner-carousel-controls">
            <button type="button" class="btn btn-outline-primary" data-bs-target="#banner-carousel" data-bs-slide="prev">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                <span class="visually-hidden">Slide sebelumnya</span>
            </button>
            <div class="carousel-indicators">
                @foreach ($banners as $banner)
                    <button type="button" data-bs-target="#banner-carousel" data-bs-slide-to="{{ $loop->index }}" @if ($loop->first) class="active" aria-current="true" @endif aria-label="Slide {{ $loop->iteration }}"></button>
                @endforeach
            </div>
            <button type="button" class="btn btn-outline-primary" data-bs-target="#banner-carousel" data-bs-slide="next">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                <span class="visually-hidden">Slide berikutnya</span>
            </button>
            <button type="button" class="btn btn-outline-primary" data-banner-toggle aria-pressed="false" hidden>
                <i class="bi bi-pause-fill" data-banner-toggle-icon aria-hidden="true"></i>
                <span data-banner-toggle-label>Jeda</span>
            </button>
        </div>
    @endif
</div>
