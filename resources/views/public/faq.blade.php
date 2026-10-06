<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'FAQ']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('faq')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @if ($groups->isEmpty())
        @if (Route::has('contact'))
            <x-empty-state title="Belum ada pertanyaan yang diterbitkan" message="Hubungi kami bila Anda memiliki pertanyaan." icon="bi-question-circle">
                <a class="btn btn-outline-primary" href="{{ route('contact') }}">Hubungi kami</a>
            </x-empty-state>
        @else
            <x-empty-state title="Belum ada pertanyaan yang diterbitkan" message="Hubungi kami bila Anda memiliki pertanyaan." icon="bi-question-circle" />
        @endif
    @else
        @foreach ($groups as $category => $faqs)
            <section class="mb-5" aria-labelledby="faq-{{ $category }}">
                <h2 class="h4 mb-3" id="faq-{{ $category }}">{{ $faqs->first()->category_label }}</h2>
                <div class="accordion" id="faq-accordion-{{ $category }}">
                    @foreach ($faqs as $faq)
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-jawaban-{{ $faq->id }}" aria-expanded="false" aria-controls="faq-jawaban-{{ $faq->id }}">{{ $faq->question }}</button>
                            </h3>
                            <div id="faq-jawaban-{{ $faq->id }}" class="accordion-collapse collapse" data-bs-parent="#faq-accordion-{{ $category }}">
                                <div class="accordion-body faq-answer">{{ $faq->answer }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if (Route::has('contact'))
            <p class="mb-0">Belum menemukan jawaban? <a href="{{ route('contact') }}">Hubungi kami</a>.</p>
        @endif
    @endif
</x-layouts.public>
