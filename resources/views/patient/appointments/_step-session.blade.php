@php
    $reasonFor = fn (\App\Enums\Availability $availability): string => match ($availability) {
        \App\Enums\Availability::Full => 'Kuota sesi sudah penuh.',
        \App\Enums\Availability::BookingClosed => 'Alasan: batas pendaftaran online '.$cutoffMinutes.' menit sebelum sesi dimulai.',
        \App\Enums\Availability::Closed => 'Sesi ditutup oleh petugas.',
        \App\Enums\Availability::UnderReview => 'Jadwal sesi sedang diperbarui.',
        \App\Enums\Availability::Leave => 'Dokter cuti, sesi tidak dapat dipesan.',
        \App\Enums\Availability::Holiday => 'Hari libur, sesi tidak dapat dipesan.',
        \App\Enums\Availability::Cancelled => 'Sesi dibatalkan.',
        default => 'Sesi tidak dapat dipesan.',
    };
@endphp
<x-form.errors-summary />
<h1 class="h3 mb-2" id="judul-langkah" tabindex="-1" data-wizard-heading>Langkah 3 - Pilih tanggal dan sesi</h1>
<p class="mb-1">Poliklinik: <strong>{{ $clinic->name }}</strong> <a href="{{ route('patient.appointments.create') }}" aria-label="Ubah poliklinik">Ubah</a></p>
<p class="mb-4">Dokter: <strong>{{ $doctor->display_name }}</strong> <a href="{{ route('patient.appointments.create', ['poli' => $clinic->slug]) }}" aria-label="Ubah dokter">Ubah</a></p>

@if ($dateEntries->isEmpty())
    <x-empty-state icon="bi-calendar-x" title="Belum ada jadwal" message="Belum ada jadwal praktik dokter ini dalam {{ $windowDays }} hari ke depan.">
        <a class="btn btn-outline-primary" href="{{ route('patient.appointments.create', ['poli' => $clinic->slug]) }}">Pilih dokter lain</a>
    </x-empty-state>
@else
    <h2 class="h5" id="judul-tanggal">Tanggal praktik ({{ $windowDays }} hari ke depan)</h2>
    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-4" aria-labelledby="judul-tanggal">
        @foreach ($dateEntries as $entry)
            <li>
                @if ($entry['selectable'])
                    <a @class(['btn btn-sm', 'btn-primary' => $selected !== null && $selected['key'] === $entry['key'], 'btn-outline-primary' => $selected === null || $selected['key'] !== $entry['key']])
                        href="{{ route('patient.appointments.create', ['poli' => $clinic->slug, 'dokter' => $doctor->slug, 'tanggal' => $entry['key']]) }}"
                        @if ($selected !== null && $selected['key'] === $entry['key']) aria-current="date" @endif>{{ $entry['date']->translatedFormat('D j M') }}</a>
                @else
                    <span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">{{ $entry['date']->translatedFormat('D j M') }} - {{ $entry['reason']->label() }}<span class="visually-hidden">, tidak dapat dipesan</span></span>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($selected === null)
        <div class="alert alert-warning" role="status">
            Tidak ada tanggal yang dapat dipesan saat ini. Silakan pilih dokter lain atau coba lagi nanti.
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('patient.appointments.create', ['poli' => $clinic->slug]) }}">Kembali</a>
    @else
        @php
            $firstBookable = $selected['items']->first(fn ($item): bool => $item->isBookableOnline);
        @endphp
        <form method="GET" action="{{ route('patient.appointments.create') }}">
            <input type="hidden" name="poli" value="{{ $clinic->slug }}">
            <input type="hidden" name="dokter" value="{{ $doctor->slug }}">
            <input type="hidden" name="tanggal" value="{{ $selected['key'] }}">

            <fieldset class="mb-4">
                <legend class="h5">Sesi pada {{ $selected['date']->translatedFormat('l, j F Y') }}</legend>
                @foreach ($selected['items'] as $item)
                    @php
                        $session = $item->session;
                        $inputId = 'sesi-'.$session->id;
                        $infoId = $inputId.'-info';
                    @endphp
                    <div @class(['form-check border rounded p-3 ps-5 mb-2', 'bg-body-tertiary' => ! $item->isBookableOnline])>
                        <input class="form-check-input" type="radio" name="sesi" id="{{ $inputId }}" value="{{ $session->id }}" aria-describedby="{{ $infoId }}"
                            @if ($item->isBookableOnline) required @else disabled @endif
                            @checked($firstBookable !== null && $firstBookable->session->id === $session->id)>
                        <label class="form-check-label fw-semibold" for="{{ $inputId }}">{{ $session->time_range }}</label>
                        <div id="{{ $infoId }}" class="small">
                            <span>Sisa {{ $item->remaining }} dari {{ $session->quota }}</span>
                            <x-availability-badge :availability="$item->availability" class="ms-2" />
                            @unless ($item->isBookableOnline)
                                <span class="d-block">{{ $reasonFor($item->availability) }}</span>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </fieldset>

            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('patient.appointments.create', ['poli' => $clinic->slug]) }}">Kembali</a>
                <x-loading-button>Lanjut: Data Pendaftaran</x-loading-button>
            </div>
        </form>
    @endif
@endif
