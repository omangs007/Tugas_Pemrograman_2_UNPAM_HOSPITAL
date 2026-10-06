@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'FAQ', 'url' => route('admin.faqs.index')],
        ['label' => 'Ubah FAQ'],
    ];
@endphp
<x-layouts.admin title="Ubah FAQ">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.faqs._form', [
        'faq' => $faq,
        'action' => route('admin.faqs.update', $faq),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
