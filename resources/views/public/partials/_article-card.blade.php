@php
    $level = min(6, max(2, (int) ($headingLevel ?? 3)));
    $heading = 'h'.$level;
    $cover = $article->firstMedia('cover');
@endphp
<article class="card h-100 article-card">
    <x-image type="article" :media="$cover" :alt="$cover?->alt_text ?: 'Cover '.$article->title" variant="thumb" ratio="16x9" />
    <div class="card-body d-flex flex-column">
        <p class="small text-body-secondary mb-2">
            @if ($article->category)
                <span>{{ $article->category->name }}</span> ·
            @endif
            <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->translatedFormat('j M Y') }}</time>
        </p>
        <{{ $heading }} class="card-title h5 mb-2">
            <a class="stretched-link" href="{{ route('articles.show', $article) }}">{{ $article->title }}</a>
        </{{ $heading }}>
        @if (filled($article->excerpt))
            <p class="card-text">{{ $article->excerpt }}</p>
        @endif
        <p class="mt-auto mb-0 small fw-semibold text-primary" aria-hidden="true">Baca selengkapnya <i class="bi bi-arrow-right" aria-hidden="true"></i></p>
    </div>
</article>
