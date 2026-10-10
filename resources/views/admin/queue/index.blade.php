{{--
    Antrean Hari Ini (W-19, F7A-R23). Variabel dari QueueController::index: $date, $loadedAt, $clinicOptions,
    $doctorOptions, $clinicSlug, $doctorSlug, $sessions, $session (atau null), $appointments, $checks (id => list<TransitionCheck>),
    $statusOrder, $statusCounts, $scan, $codeInput, $highlightId, $returnUrl, $ended, $underReview, $pendingCount,
    $showBanner, $awaiting, $canManage, $canMarkNoShow. Aksi baris memakai partial admin.appointments._actions dengan
    $context = 'queue' (Konfirmasi/Check-in/Selesai = form POST; Batal/Koreksi = tautan ke detail; check-in belum dibuka =
    tombol aria-disabled + "Dibuka HH.MM"). Tanpa JS baru dan tanpa pembaruan otomatis.
--}}
<x-layouts.admin title="Antrean Hari Ini">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Pendaftaran', 'url' => route('admin.appointments.index')],
            ['label' => 'Antrean Hari Ini'],
        ]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <span class="small text-body-secondary align-self-center" data-loaded-at>Dimuat pukul {{ $loadedAt->format('H.i') }} WIB</span>
        <a href="{{ $returnUrl }}" class="btn btn-outline-dark"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Muat ulang</a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="appointments" />
    </div>

    <section class="card card-body bg-light border-0 mb-4" aria-labelledby="judul-pilih-sesi">
        <h2 class="h5" id="judul-pilih-sesi">Pilih sesi dan cari</h2>

        <form method="GET" action="{{ route('admin.queue.index') }}" aria-label="Pilih tanggal dan sesi" class="row g-3 align-items-end">
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-tanggal" class="form-label fw-semibold">Tanggal</label>
                <input type="date" id="filter-tanggal" name="tanggal" value="{{ $date->toDateString() }}" class="form-control">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-poli" class="form-label fw-semibold">Poliklinik</label>
                <select id="filter-poli" name="poli" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($clinicOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected($clinicSlug === (string) $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-dokter" class="form-label fw-semibold">Dokter</label>
                <select id="filter-dokter" name="dokter" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($doctorOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected($doctorSlug === (string) $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <label for="filter-sesi" class="form-label fw-semibold">Sesi</label>
                <select id="filter-sesi" name="sesi" class="form-select">
                    @forelse ($sessions as $option)
                        <option value="{{ $option->id }}" @selected($session !== null && $session->id === $option->id)>{{ $option->clinic->name }} - {{ $option->doctor->display_name }} - {{ $option->time_range }} - {{ $option->booked_count }}/{{ $option->quota }} terisi{{ $option->status !== \App\Enums\SessionStatus::Open ? ' ('.$option->status->label().')' : '' }}</option>
                    @empty
                        <option value="">Tidak ada sesi</option>
                    @endforelse
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <x-loading-button icon="bi-calendar-check">Tampilkan</x-loading-button>
            </div>
        </form>

        <form method="GET" action="{{ route('admin.queue.index') }}" role="search" aria-label="Cari atau pindai kode booking" class="row g-3 align-items-end mt-1">
            <input type="hidden" name="tanggal" value="{{ $date->toDateString() }}">
            @if ($session !== null)
                <input type="hidden" name="sesi" value="{{ $session->id }}">
            @endif
            @if ($clinicSlug !== null)
                <input type="hidden" name="poli" value="{{ $clinicSlug }}">
            @endif
            @if ($doctorSlug !== null)
                <input type="hidden" name="dokter" value="{{ $doctorSlug }}">
            @endif
            <div class="col-12 col-md-6 col-lg-4">
                <label for="filter-kode" class="form-label fw-semibold">Cari atau pindai kode booking</label>
                <input type="search" id="filter-kode" name="kode" value="{{ $codeInput }}" class="form-control font-monospace" placeholder="UPH-XXXX-XXXX" maxlength="100" autocomplete="off" autofocus aria-describedby="kode-bantuan">
                <div class="form-text" id="kode-bantuan">Pemindai USB mengetik kode lalu menekan Enter. Pemindaian hanya menemukan dan menyorot baris; check-in tetap dilakukan dengan menekan tombol.</div>
            </div>
            <div class="col-12 col-md-auto">
                <x-loading-button icon="bi-search">Cari</x-loading-button>
            </div>
        </form>

        <div aria-live="polite">
            @if ($scan !== null)
                @if ($scan['type'] === 'found')
                    <div class="alert alert-success mt-3 mb-0" role="status" data-scan-result="found">{{ $scan['message'] }}</div>
                @elseif ($scan['type'] === 'other-date')
                    <div class="alert alert-info mt-3 mb-0" role="status" data-scan-result="other-date">{{ $scan['message'] }} <a href="{{ $scan['url'] }}" class="alert-link">Buka antrean tanggal itu</a></div>
                @else
                    <div class="alert alert-warning mt-3 mb-0" role="alert" data-scan-result="missing">{{ $scan['message'] }}</div>
                @endif
            @endif
        </div>
    </section>

    @if ($session === null)
        <p class="text-body-secondary">Antrean untuk <time datetime="{{ $date->toDateString() }}">{{ $date->translatedFormat('l, j F Y') }}</time>.</p>
        <x-empty-state title="Tidak ada sesi praktik pada tanggal ini" message="Pilih tanggal lain atau ubah filter poliklinik dan dokter." icon="bi-calendar-x" />
    @else
        <section aria-labelledby="judul-antrean">
            <h2 class="h5 mb-1" id="judul-antrean">{{ $session->clinic->name }} - {{ $session->doctor->display_name }}</h2>
            <p class="text-body-secondary mb-3">
                <time datetime="{{ $date->toDateString() }}">{{ $date->translatedFormat('l, j F Y') }}</time>, {{ $session->time_range }}
                @if ($session->status !== \App\Enums\SessionStatus::Open)
                    <x-status-badge :status="$session->status" class="ms-1" />
                @endif
            </p>

            <div class="d-flex flex-wrap align-items-center gap-3 mb-3" data-queue-summary>
                <p class="mb-0 fw-semibold">Terisi {{ $session->booked_count }} dari {{ $session->quota }}</p>
                <ul class="list-inline mb-0">
                    @foreach ($statusOrder as $status)
                        <li class="list-inline-item" data-status-count="{{ $status->value }}"><x-status-badge :status="$status" /> <span class="fw-semibold">{{ $statusCounts[$status->value] ?? 0 }}</span></li>
                    @endforeach
                </ul>
            </div>

            @if ($underReview)
                <div class="alert alert-warning" role="status" data-under-review>{{ __('booking.transitions.SESSION_UNDER_REVIEW') }}</div>
            @elseif ($showBanner)
                <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status" data-no-show-banner>
                    <div><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Sesi telah berakhir pukul {{ $session->ends_at->format('H.i') }}. {{ $pendingCount }} pendaftaran belum check-in (Menunggu / Dikonfirmasi).</div>
                    @if ($canMarkNoShow)
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modal-tidak-hadir"><i class="bi bi-person-x" aria-hidden="true"></i> Tandai tidak hadir ({{ $pendingCount }})</button>
                    @endif
                </div>
            @endif

            @if ($appointments->isEmpty())
                <x-empty-state title="Belum ada pendaftaran pada sesi ini" message="Pendaftaran baru muncul di sini setelah dibuat. Muat ulang untuk melihat yang terbaru." icon="bi-inbox" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-cards bg-white">
                        <caption class="visually-hidden">Antrean {{ $session->clinic->name }}, {{ $session->doctor->display_name }}, {{ $date->translatedFormat('j F Y') }}</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Antrean</th>
                                <th scope="col">Kode booking</th>
                                <th scope="col">Pasien</th>
                                <th scope="col">Pembayaran</th>
                                <th scope="col">Status</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($appointments as $appointment)
                                @php
                                    $highlighted = $highlightId !== null && $highlightId === (int) $appointment->id;
                                    $detailUrl = route('admin.appointments.show', ['appointment' => $appointment->booking_code, 'kembali' => $returnUrl]);
                                @endphp
                                <tr id="baris-{{ $appointment->id }}"@if ($highlighted) class="table-warning" aria-current="true"@endif>
                                    <td data-label="Antrean" class="fw-semibold">{{ $appointment->queue_label }}</td>
                                    <td data-label="Kode booking"><a href="{{ $detailUrl }}" class="font-monospace">{{ $appointment->booking_code }}</a></td>
                                    <td data-label="Pasien">
                                        {{ $appointment->patientProfile->full_name }}
                                        <span class="d-block small text-body-secondary">{{ $appointment->patientProfile->patient_number ?? '-' }}</span>
                                    </td>
                                    <td data-label="Pembayaran">{{ $appointment->payment_type->label() }}</td>
                                    <td data-label="Status"><x-status-badge :status="$appointment->status" /></td>
                                    <td data-label="Aksi" class="table-actions">
                                        @if ($canManage)
                                            @include('admin.appointments._actions', ['appointment' => $appointment, 'checks' => $checks[$appointment->id] ?? [], 'context' => 'queue', 'returnTo' => $returnUrl])
                                        @else
                                            <span class="text-body-secondary">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-body-secondary">Urut nomor antrean; nomor yang dibatalkan tidak dipakai ulang. Check-in nonaktif sebelum jam dibuka, dengan teks jam dibuka di sampingnya. Aksi yang memerlukan alasan dilakukan lewat halaman detail pendaftaran.</p>
            @endif
        </section>

        @if ($showBanner && $canMarkNoShow)
            <x-confirm-modal
                id="modal-tidak-hadir"
                :title="'Tandai tidak hadir ('.$pendingCount.')?'"
                :message="$pendingCount.' pendaftaran berikut akan ditandai tidak hadir. Status tidak hadir bersifat akhir dan tidak dapat diubah lagi; pasien berakun menerima pemberitahuan.'"
                confirm-label="Ya, tandai tidak hadir"
                variant="danger"
                :action="route('admin.queue.mark-no-show', ['session' => $session->id])">
                <ul class="list-group mb-3" data-no-show-list>
                    @foreach ($awaiting as $waiting)
                        <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span>{{ $waiting->queue_label }} {{ $waiting->patientProfile->full_name }}</span>
                            <x-status-badge :status="$waiting->status" />
                        </li>
                    @endforeach
                </ul>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="confirm" id="tidak-hadir-konfirmasi" value="1" required aria-required="true">
                    <label class="form-check-label" for="tidak-hadir-konfirmasi">Saya menegaskan seluruh pendaftaran di atas ditandai tidak hadir.</label>
                </div>
                <div class="mb-0">
                    <label for="tidak-hadir-alasan" class="form-label fw-semibold">Alasan (opsional)</label>
                    <textarea class="form-control" id="tidak-hadir-alasan" name="reason" rows="2" maxlength="255" aria-describedby="tidak-hadir-alasan-bantuan">{{ old('reason') }}</textarea>
                    <div class="form-text" id="tidak-hadir-alasan-bantuan">Maksimal 255 karakter. Alasan tercatat pada riwayat status tiap pendaftaran.</div>
                </div>
            </x-confirm-modal>
        @endif
    @endif
</x-layouts.admin>
