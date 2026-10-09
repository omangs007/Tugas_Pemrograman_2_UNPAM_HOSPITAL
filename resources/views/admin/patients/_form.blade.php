{{-- Variabel: $patient (PatientProfile|null), $action, $submitLabel, $cancelUrl, $kembali (string|null),
     $similar (koleksi PatientProfile; hanya form tambah), $pickUrls (id => URL|null).
     Tanpa NIK, tanpa nomor BPJS, tanpa akun login (F7A-R8). `kembali` hanya berasal dari controller (sudah dibersihkan). --}}
@php
    $isEdit = $patient !== null;
    $hasAccount = $isEdit && $patient->user_id !== null;
    $showSimilar = ! $isEdit && ($similar->isNotEmpty() || $errors->has('confirm_not_duplicate'));
@endphp
<form method="POST" action="{{ $action }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    @if ($kembali !== null)
        <input type="hidden" name="kembali" value="{{ $kembali }}">
    @endif

    <x-form.errors-summary />

    @if ($showSimilar)
        <section class="card border-warning mb-4" aria-labelledby="judul-profil-mirip">
            <div class="card-header fw-semibold"><h2 class="h6 mb-0" id="judul-profil-mirip">Profil yang mirip ditemukan</h2></div>
            <div class="card-body">
                <p>Pilih profil yang sudah ada agar tidak terjadi data ganda, atau centang konfirmasi di bawah untuk tetap membuat profil baru.</p>
                @if ($similar->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm align-middle table-cards bg-white mb-3">
                            <caption class="visually-hidden">Profil pasien yang mirip dengan data yang diisi</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Nama</th>
                                    <th scope="col">No. pasien</th>
                                    <th scope="col">Tanggal lahir</th>
                                    <th scope="col">Telepon</th>
                                    <th scope="col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($similar as $candidate)
                                    <tr>
                                        <td data-label="Nama">{{ $candidate->full_name }}</td>
                                        <td data-label="No. pasien">{{ $candidate->patient_number ?? '—' }}</td>
                                        <td data-label="Tanggal lahir">{{ $candidate->birth_date->format('d/m/Y') }}</td>
                                        <td data-label="Telepon">{{ $candidate->phone }}</td>
                                        <td data-label="Aksi" class="table-actions">
                                            <a href="{{ route('admin.patients.show', $candidate) }}" class="btn btn-sm btn-outline-secondary" aria-label="Lihat detail {{ $candidate->full_name }}">Lihat detail</a>
                                            @if (($pickUrls[$candidate->id] ?? null) !== null)
                                                <a href="{{ $pickUrls[$candidate->id] }}" class="btn btn-sm btn-primary" aria-label="Pilih {{ $candidate->full_name }} untuk pendaftaran">Pilih</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <x-form.checkbox name="confirm_not_duplicate" label="Tetap buat profil baru" />
            </div>
        </section>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <x-form.input name="full_name" label="Nama lengkap" :value="$patient?->full_name" :required="true" maxlength="100" autocomplete="off" help="Sesuai kartu identitas pasien; maksimal 100 karakter." />
            <x-form.input name="birth_date" type="date" label="Tanggal lahir" :value="$patient?->birth_date?->toDateString()" :required="true" :min="today()->subYears(120)->toDateString()" :max="today()->subDay()->toDateString()" help="Pilih dari kalender atau ketik dengan urutan tahun-bulan-tanggal." />
            <x-form.radio-group name="gender" legend="Jenis kelamin" :options="\App\Enums\Gender::options()" :selected="$patient?->gender" :inline="true" :required="true" />
            <x-form.input name="phone" type="tel" label="Nomor telepon" :value="$patient?->phone" :required="true" inputmode="tel" maxlength="20" autocomplete="off" help="Nomor seluler Indonesia, mis. 081234567890." />
            @if ($hasAccount)
                <div class="mb-3">
                    <label for="email-akun" class="form-label fw-semibold">Email</label>
                    <input type="text" id="email-akun" class="form-control" value="{{ $patient->email }}" readonly aria-describedby="email-akun-help">
                    <div id="email-akun-help" class="form-text">Email akun tidak dapat diubah dari layar admin. Pasien mengubahnya sendiri dari profilnya.</div>
                </div>
            @else
                <x-form.input name="email" type="email" label="Email (opsional)" :value="$patient?->email" maxlength="255" autocomplete="off" help="Hanya sebagai kontak; tidak dipakai untuk masuk ke akun." />
            @endif
            <x-form.textarea name="address" label="Alamat" :value="$patient?->address" :rows="3" maxlength="500" :required="true" help="Minimal 10 karakter, maksimal 500 karakter." />

            @unless ($isEdit)
                <x-form.checkbox name="verbal_consent" label="Pasien telah diberi tahu mengenai Kebijakan Privasi dan menyetujui pengolahan datanya (persetujuan lisan)" :required="true" help="Wajib dicentang petugas setelah kebijakan privasi dibacakan kepada pasien." />
                <p class="form-text mb-0">Tanpa NIK dan tanpa akun login. Nomor BPJS diisi saat mendaftarkan kunjungan pasien.</p>
            @endunless
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
