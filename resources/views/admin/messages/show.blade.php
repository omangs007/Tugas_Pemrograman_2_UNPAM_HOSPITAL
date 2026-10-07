@php
    $status = $contactMessage->status;
    $statusUrl = route('admin.messages.status', $contactMessage);
    $format = fn (?\Carbon\CarbonInterface $at): string => $at === null ? '—' : $at->translatedFormat('j M Y, H.i');
    $phoneLink = preg_replace('/[^0-9+]/', '', (string) $contactMessage->phone);
    $lines = preg_split('/\R/u', (string) $contactMessage->message) ?: [];
    $breadcrumbItems = [
        ['label' => 'Pesan Kontak', 'url' => route('admin.messages.index')],
        ['label' => $contactMessage->subject],
    ];
    // Tombol status menurut status saat ini (F4B-R24): [nilai tujuan, label, varian tombol, ikon].
    $actions = match ($status) {
        \App\Enums\ContactMessageStatus::New, \App\Enums\ContactMessageStatus::Read => [
            ['replied', 'Tandai Dibalas', 'primary', 'bi-reply'],
            ['archived', 'Arsipkan', 'outline-secondary', 'bi-archive'],
        ],
        \App\Enums\ContactMessageStatus::Replied => [
            ['archived', 'Arsipkan', 'outline-secondary', 'bi-archive'],
        ],
        \App\Enums\ContactMessageStatus::Archived => [
            ['read', 'Pulihkan', 'outline-primary', 'bi-arrow-counterclockwise'],
        ],
    };
@endphp
<x-layouts.admin :title="'Pesan: '.$contactMessage->subject">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Isi pesan</div>
                <div class="card-body">
                    <h2 class="h5">{{ $contactMessage->subject }}</h2>
                    {{-- Hanya {{ }}: tiap baris di-escape, baris baru dipertahankan dengan <br> (BR-122, SEK-15). --}}
                    <div class="message-body text-break">
                        @foreach ($lines as $line)
                            {{ $line }}@if (! $loop->last)<br>@endif
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($actions as [$target, $label, $variant, $icon])
                    <form method="POST" action="{{ $statusUrl }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $target }}">
                        <x-loading-button :variant="$variant" :icon="$icon" loading-text="Menyimpan…">{{ $label }}</x-loading-button>
                    </form>
                @endforeach
            </div>

            <p class="text-secondary">Balas pesan ini secara manual lewat email atau telepon. Aplikasi tidak mengirim balasan otomatis.</p>

            <a href="{{ route('admin.messages.index') }}" class="btn btn-link px-0">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar pesan
            </a>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header fw-semibold">Rincian</div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Pengirim</dt>
                        <dd>{{ $contactMessage->name }}</dd>
                        <dt>Email</dt>
                        <dd><a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a></dd>
                        @if ($phoneLink !== '')
                            <dt>Telepon</dt>
                            <dd><a href="tel:{{ $phoneLink }}">{{ $contactMessage->phone }}</a></dd>
                        @endif
                        <dt>Diterima</dt>
                        <dd><time datetime="{{ $contactMessage->created_at->toIso8601String() }}">{{ $format($contactMessage->created_at) }}</time></dd>
                        <dt>Status</dt>
                        <dd><x-status-badge :status="$status" /></dd>
                        @if ($contactMessage->handled_at !== null)
                            <dt>Ditangani</dt>
                            <dd>oleh {{ $contactMessage->handler?->name ?? 'akun yang sudah dihapus' }} pada {{ $format($contactMessage->handled_at) }}</dd>
                        @endif
                        <dt>Persetujuan</dt>
                        <dd>{{ $format($contactMessage->consent_at) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
