<x-layouts.admin title="Dasbor">
    <p class="lead mb-1">Selamat datang, {{ $user->name }}.</p>
    <p class="mb-4">Peran Anda: <strong>{{ $user->roleLabel }}</strong>.</p>
    <p class="text-body-secondary mb-0">Ringkasan angka dan grafik akan ditambahkan pada fase berikutnya.</p>
</x-layouts.admin>
