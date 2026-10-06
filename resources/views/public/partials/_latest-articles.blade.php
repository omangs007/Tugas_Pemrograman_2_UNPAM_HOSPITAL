@if ($articles->isNotEmpty())
    <section class="home-section" aria-labelledby="judul-artikel-terbaru">
        <div class="home-section-header">
            <h2 id="judul-artikel-terbaru" class="mb-0">Artikel Terbaru</h2>
            @if (Route::has('articles.index'))
                <a href="{{ route('articles.index') }}">Lihat semua artikel</a>
            @endif
        </div>
        <div class="row g-4">
            @foreach ($articles as $article)
                <div class="col-12 col-md-6 col-lg-4">
                    @include('public.partials._article-card', ['article' => $article, 'headingLevel' => 3])
                </div>
            @endforeach
        </div>
    </section>
@endif
