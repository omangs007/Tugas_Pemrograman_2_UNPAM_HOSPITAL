@php
    $reasonFor = fn (\App\Enums\Availability $availability): string => match ($availability) {
        \App\Enums\Availability::Full => 'Kuota sesi sudah penuh.',
        \App\Enums\Availability::BookingClosed => 'Di luar jendela pendaftaran ('.$windowDays.' hari ke depan).',
        \App\Enums\Availability::Closed => 'Sesi ditutup oleh petugas.',
        \App\Enums\Availability::UnderReview => 'Jadwal sesi sedang diperbarui.',
        \App\Enums\Availability::Leave => 'Dokter cuti.',
        \App\Enums\Availability::Holiday => 'Hari libur.',
        \App\Enums\Availability::Cancelled => 'Sesi dibatalkan.',
        default => 'Sesi tidak dapat didaftarkan.',
    };
@endphp
<section class="card mb-4" aria-labelledby="judul-sesi">
    <div class="card-body">
        <h2 class="h5" id="judul-sesi">2. Sesi</h2>

        @if ($profile === null)
            <p class="text-body-secondary mb-0" aria-disabled="true">Pilih pasien terlebih dahulu.</p>
        @else
            <form method="GET" action="{{ route('admin.appointments.create') }}" class="row g-3 align-items-end mb-3">
                <input type="hidden" name="pasien" value="{{ $profile->id }}">
                @if ($kembali)
                    <input type="hidden" name="kembali" value="{{ $kembali }}">
                @endif
                <div class="col-12 col-md-4">
                    <label for="poli" class="form-label fw-semibold">Poliklinik</label>
                    <select id="poli" name="poli" class="form-select">
                        <option value="">Pilih poliklinik</option>
                        @foreach ($clinicOptions as $slug => $name)
                            <option value="{{ $slug }}" @selected($clinic !== null && $clinic->slug === $slug)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="dokter" class="form-label fw-semibold">Dokter</label>
                    <select id="dokter" name="dokter" class="form-select" @if ($clinic === null) aria-describedby="dokter-bantuan" @endif>
                        <option value="">{{ $clinic === null ? 'Pilih poliklinik lalu tekan Tampilkan' : 'Pilih dokter' }}</option>
                        @foreach ($doctorOptions as $slug => $name)
                            <option value="{{ $slug }}" @selected($doctor !== null && $doctor->slug === $slug)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @if ($clinic === null)
                        <div class="form-text" id="dokter-bantuan">Daftar dokter muncul setelah poliklinik dipilih.</div>
                    @endif
                </div>
                <div class="col-12 col-md-2">
                    <label for="tanggal" class="form-label fw-semibold">Tanggal</label>
                    <input type="date" id="tanggal" name="tanggal" value="{{ $date }}" min="{{ $minDate }}" max="{{ $maxDate }}" class="form-control">
                </div>
                <div class="col-12 col-md-auto">
                    <x-loading-button icon="bi-calendar-event" loading-text="Memuat…">Tampilkan Sesi</x-loading-button>
                </div>
            </form>

            @if ($clinic !== null && $doctor !== null)
                @if ($items->isEmpty())
                    <x-empty-state icon="bi-calendar-x" title="Tidak ada sesi pada tanggal ini" message="Pilih tanggal atau dokter lain. Sesi yang sudah berakhir tidak ditampilkan." />
                @else
                    <form method="GET" action="{{ route('admin.appointments.create') }}">
                        <input type="hidden" name="pasien" value="{{ $profile->id }}">
                        <input type="hidden" name="poli" value="{{ $clinic->slug }}">
                        <input type="hidden" name="dokter" value="{{ $doctor->slug }}">
                        <input type="hidden" name="tanggal" value="{{ $date }}">
                        @if ($kembali)
                            <input type="hidden" name="kembali" value="{{ $kembali }}">
                        @endif

                        <fieldset class="mb-3">
                            <legend class="h6">Sesi pada {{ \Carbon\CarbonImmutable::parse($date)->translatedFormat('l, j F Y') }}</legend>
                            @foreach ($items as $item)
                                @php
                                    $session = $item->session;
                                    $inputId = 'sesi-'.$session->id;
                                    $infoId = $inputId.'-info';
                                    $selected = $chosenItem !== null && $chosenItem->session->id === $session->id;
                                @endphp
                                <div @class(['form-check border rounded p-3 ps-5 mb-2', 'bg-body-tertiary' => ! $item->isBookableCounter])>
                                    <input class="form-check-input" type="radio" name="sesi" id="{{ $inputId }}" value="{{ $session->id }}" aria-describedby="{{ $infoId }}" @if (! $item->isBookableCounter) disabled @endif @checked($selected)>
                                    <label class="form-check-label fw-semibold" for="{{ $inputId }}">{{ $session->time_range }}</label>
                                    <div id="{{ $infoId }}" class="small">
                                        <span>Sisa {{ $item->remaining }} dari {{ $session->quota }}</span>
                                        <x-availability-badge :availability="$item->availability" class="ms-2" />
                                        @if (! $item->isBookableOnline && $item->isBookableCounter)
                                            <span class="d-block">Online ditutup; loket masih bisa</span>
                                        @endif
                                        @unless ($item->isBookableCounter)
                                            <span class="d-block">{{ $reasonFor($item->availability) }}</span>
                                        @endunless
                                    </div>
                                </div>
                            @endforeach
                            <p class="form-text">Loket boleh mendaftar sampai jam selesai sesi.</p>
                        </fieldset>

                        <x-loading-button icon="bi-check2-square" loading-text="Memuat…">Pilih Sesi</x-loading-button>
                    </form>
                @endif
            @endif
        @endif
    </div>
</section>
