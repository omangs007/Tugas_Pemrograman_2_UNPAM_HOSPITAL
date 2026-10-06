<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Layanan']]" />
    </x-slot:breadcrumb>

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @forelse ($groups as $group)
        <section class="mb-5" aria-labelledby="layanan-{{ $group['type']->value }}">
            <h2 class="h3 mb-3" id="layanan-{{ $group['type']->value }}">
                <i class="bi {{ $group['type']->icon() }} me-2" aria-hidden="true"></i>{{ $group['type']->label() }}
            </h2>
            <div class="row g-4">
                @foreach ($group['clinics'] as $clinic)
                    <div class="col-12 col-sm-6 col-lg-4">
                        @include('public.partials._clinic-card', ['clinic' => $clinic])
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <x-empty-state title="Belum ada layanan" message="Daftar layanan akan tampil di sini setelah data tersedia." icon="bi-hospital" />
    @endforelse
</x-layouts.public>
