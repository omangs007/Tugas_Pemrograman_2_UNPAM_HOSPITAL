{{-- Panel hubungkan akun (F7A-R12). Variabel: $patient, $linkPanel (array|null dari PatientController::linkPanel).
     Perbandingan identitas dan form POST hanya bila akun memenuhi syarat; selain itu hanya pesan penolakan. --}}
@if ($linkPanel !== null)
    @if ($linkPanel['inactive'])
        <p class="text-body-secondary mt-4 mb-0" id="hubungkan-akun-nonaktif">Aktifkan profil terlebih dahulu untuk menghubungkannya ke akun online.</p>
    @else
        @php $account = $linkPanel['account']; @endphp
        <section id="hubungkan-akun" class="card mt-4" aria-labelledby="hubungkan-akun-judul">
            <div class="card-header fw-semibold"><h2 class="h6 mb-0" id="hubungkan-akun-judul">Hubungkan ke akun online</h2></div>
            <div class="card-body">
                <p>Gunakan setelah identitas pasien diverifikasi di loket. Tidak ada pencocokan otomatis: masukkan email akun yang ditunjukkan pasien, lalu periksa kecocokan datanya.</p>

                <form method="GET" action="{{ route('admin.patients.show', $patient) }}#hubungkan-akun" class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-8">
                        <label for="hubungkan-email" class="form-label fw-semibold">Email akun pasien</label>
                        <input type="email" id="hubungkan-email" name="hubungkan" value="{{ $linkPanel['email'] }}" class="form-control" maxlength="255" autocomplete="off" aria-describedby="hubungkan-email-help">
                        <div id="hubungkan-email-help" class="form-text">Email yang dipakai pasien untuk masuk ke portal pasien.</div>
                    </div>
                    <div class="col-12 col-md-auto">
                        <x-loading-button icon="bi-search" loading-text="Mencari…">Cari Akun</x-loading-button>
                    </div>
                </form>

                @if ($linkPanel['invalid'] !== null)
                    <div class="alert alert-warning mb-0" role="alert">{{ $linkPanel['invalid'] }}</div>
                @elseif ($linkPanel['problem'] !== null)
                    <div class="alert alert-warning mb-0" role="alert">{{ $linkPanel['problem'] }}</div>
                @elseif ($account !== null)
                    @php $accountProfile = $account->patientProfile; @endphp
                    <h3 class="h6">Periksa kecocokan identitas</h3>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle table-cards bg-white">
                            <caption class="visually-hidden">Perbandingan data profil walk-in dan akun online</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Data</th>
                                    <th scope="col">Profil walk-in</th>
                                    <th scope="col">Akun online</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th scope="row">Nama lengkap</th>
                                    <td data-label="Profil walk-in">{{ $patient->full_name }}</td>
                                    <td data-label="Akun online">{{ $account->name }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Tanggal lahir</th>
                                    <td data-label="Profil walk-in">{{ $patient->birth_date->format('d/m/Y') }}</td>
                                    <td data-label="Akun online">{{ $accountProfile?->birth_date?->format('d/m/Y') ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">Telepon</th>
                                    <td data-label="Profil walk-in">{{ $patient->phone }}</td>
                                    <td data-label="Akun online">{{ $account->phone ?? $accountProfile?->phone ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @if ($accountProfile !== null)
                        <p class="small text-body-secondary">Profil kosong milik akun ini ({{ $accountProfile->patient_number ?? 'tanpa nomor' }}) akan dihapus; nomor pasien dan data profil walk-in tetap dipakai.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.patients.link-account', $patient) }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="account_email" value="{{ $linkPanel['email'] }}">
                        <x-form.checkbox name="identity_verified" label="Saya telah memverifikasi identitas pasien di loket" :required="true" help="Setelah dihubungkan, pasien melihat riwayat walk-in saat masuk ke akunnya. Tindakan ini tidak dapat dibatalkan." />
                        @if ($errors->has('account_email'))
                            <div class="alert alert-danger" role="alert">{{ $errors->first('account_email') }}</div>
                        @endif
                        <x-loading-button icon="bi-link-45deg" loading-text="Menghubungkan…">Hubungkan Akun</x-loading-button>
                    </form>
                @endif
            </div>
        </section>
    @endif
@endif
