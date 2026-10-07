<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Kamar Rawat Inap']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('rooms.index')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @include('public.rooms._notice')

    @if ($rooms->isEmpty())
        <x-empty-state title="Belum ada kategori kamar" message="Informasi kamar rawat inap akan tampil di sini setelah ditambahkan." icon="bi-building" />
    @else
        <div class="row g-4 row-cols-1 row-cols-lg-2">
            @foreach ($rooms as $room)
                @php
                    $cover = $room->firstMedia('cover');
                @endphp
                <div class="col">
                    <article class="card h-100 room-card">
                        <x-image type="room" :media="$cover" :alt="$cover?->alt_text ?: $room->name" variant="thumb" ratio="4x3" />
                        <div class="card-body d-flex flex-column">
                            <h2 class="card-title h5 mb-1">
                                <a class="stretched-link" href="{{ route('rooms.show', $room) }}">{{ $room->name }}</a>
                            </h2>
                            @if (filled($room->short_description))
                                <p class="card-text mb-2">{{ $room->short_description }}</p>
                            @endif
                            <ul class="list-unstyled mb-3">
                                <li>Kapasitas: {{ $room->bed_capacity }} tempat tidur</li>
                                <li>{{ $room->occupancy_label }} @include('public.rooms._availability-badge', ['room' => $room])</li>
                                <li>Tarif contoh: {{ $room->sample_rate_label }}</li>
                                <li class="small text-body-secondary">{{ $room->availability_updated_label }}</li>
                            </ul>
                            @if ($room->is_availability_stale)
                                <p class="small text-warning-emphasis mb-3">Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan.</p>
                            @endif
                            <span class="btn btn-outline-primary mt-auto" aria-hidden="true">Lihat Detail</span>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.public>
