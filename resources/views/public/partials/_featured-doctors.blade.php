@if ($doctors->isNotEmpty())
    <section class="home-section" aria-labelledby="judul-dokter-kami">
        <div class="home-section-header">
            <h2 id="judul-dokter-kami" class="mb-0">Dokter Kami</h2>
            @if (Route::has('doctors.index'))
                <a href="{{ route('doctors.index') }}">Lihat semua dokter</a>
            @endif
        </div>
        <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-xl-4">
            @foreach ($doctors as $doctor)
                <div class="col">
                    <x-doctor-card :doctor="$doctor" :heading-level="3" :show-clinic="true" :next-session="$nextSessions[$doctor->id] ?? null" />
                </div>
            @endforeach
        </div>
    </section>
@endif
