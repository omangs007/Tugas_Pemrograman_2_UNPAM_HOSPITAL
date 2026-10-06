@php
    $cover = $article->firstMedia('cover');
    $subtitle = collect([
        $article->category?->name,
        $article->published_at->translatedFormat('j F Y'),
        '± '.$article->reading_time_minutes.' menit baca',
    ])->filter()->implode(' · ');
@endphp
<x-layouts.public :title="$article->seo_title" :meta-description="$article->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Artikel', 'url' => route('articles.index')], ['label' => $article->title]]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$article->seo_title" :description="$article->seo_description" :image="$cover?->url ? url($cover->url) : null" type="article" :canonical="route('articles.show', $article)" />
    @endpush

    <x-page-header :title="$article->title" :subtitle="$subtitle" />

    @if ($cover)
        <div class="mb-4">
            <x-image type="article" :media="$cover" :alt="$cover->alt_text ?: 'Cover '.$article->title" ratio="16x9" :lazy="false" />
        </div>
    @endif

    <div class="rich-text article-body">{!! $article->body !!}</div>

    @if ($article->author)
        <p class="text-body-secondary mt-4 mb-0">Ditulis oleh {{ $article->author->name }}</p>
    @endif

    @if ($related->isNotEmpty())
        <section class="mt-5" aria-labelledby="terkait-judul">
            <h2 id="terkait-judul" class="h4 mb-3">Artikel Terkait</h2>
            <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3">
                @foreach ($related as $item)
                    <div class="col">
                        @include('public.partials._article-card', ['article' => $item, 'headingLevel' => 3])
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <p class="mt-5 mb-0">
        <a class="btn btn-outline-primary" href="{{ route('articles.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar artikel</a>
    </p>
</x-layouts.public>
