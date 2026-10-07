@if ($rooms->isNotEmpty())
    <section class="home-section" aria-labelledby="judul-kamar">
        <div class="home-section-header">
            <h2 id="judul-kamar" class="mb-0">Ketersediaan Kamar Rawat Inap</h2>
            @if (Route::has('rooms.index'))
                <a href="{{ route('rooms.index') }}">Lihat detail kamar</a>
            @endif
        </div>
        <p class="text-body-secondary">Diperbarui manual oleh petugas. Hubungi rumah sakit untuk memastikan ketersediaan.</p>
        <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-xl-4">
            @foreach ($rooms as $room)
                <div class="col">
                    <article class="card h-100 room-card">
                        <div class="card-body">
                            <h3 class="card-title h5 mb-2">
                                <a class="stretched-link" href="{{ route('rooms.show', $room) }}">{{ $room->name }}</a>
                            </h3>
                            <p class="mb-1">{{ $room->occupancy_label }}</p>
                            <p class="mb-2">@include('public.rooms._availability-badge', ['room' => $room])</p>
                            <p class="small text-body-secondary mb-0">{{ $room->availability_updated_label }}</p>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </section>
@endif
