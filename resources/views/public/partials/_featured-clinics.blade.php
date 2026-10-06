@if ($clinics->isNotEmpty())
    <section class="home-section" aria-labelledby="judul-layanan-unggulan">
        <div class="home-section-header">
            <h2 id="judul-layanan-unggulan" class="mb-0">Layanan Unggulan</h2>
            @if (Route::has('clinics.index'))
                <a href="{{ route('clinics.index') }}">Lihat semua layanan</a>
            @endif
        </div>
        <div class="row g-4">
            @foreach ($clinics as $clinic)
                <div class="col-12 col-sm-6 col-lg-4">
                    @include('public.partials._clinic-card', ['clinic' => $clinic])
                </div>
            @endforeach
        </div>
    </section>
@endif
