<section class="card mb-4" aria-labelledby="judul-pasien">
    <div class="card-body">
        <h2 class="h5" id="judul-pasien">1. Pasien</h2>

        <form method="GET" action="{{ route('admin.appointments.create') }}" role="search" aria-label="Cari pasien" class="row g-2 align-items-end mb-3">
            @if ($kembali)
                <input type="hidden" name="kembali" value="{{ $kembali }}">
            @endif
            <div class="col-12 col-md-8">
                <label for="cari" class="form-label fw-semibold">Cari pasien (nama, no. pasien, telepon, atau tanggal lahir dd/mm/yyyy)</label>
                <input type="search" id="cari" name="cari" value="{{ $term }}" maxlength="100" autocomplete="off" class="form-control">
            </div>
            <div class="col-12 col-md-auto">
                <x-loading-button icon="bi-search" loading-text="Mencari…">Cari</x-loading-button>
            </div>
        </form>

        @if ($term !== null)
            <p class="small text-secondary" role="status" aria-live="polite">
                @if ($results->isEmpty())
                    Pasien tidak ditemukan.
                @else
                    Menampilkan {{ $results->count() }} pasien (maksimal 10).
                @endif
            </p>

            @if ($results->isNotEmpty())
                <div class="table-responsive">
                    <table class="table align-middle table-cards bg-white">
                        <caption class="visually-hidden">Hasil pencarian pasien</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Nama</th>
                                <th scope="col">No. pasien</th>
                                <th scope="col">Telepon</th>
                                <th scope="col">Lahir</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $result)
                                <tr>
                                    <td data-label="Nama">{{ $result->full_name }}</td>
                                    <td data-label="No. pasien">{{ $result->patient_number ?? '-' }}</td>
                                    <td data-label="Telepon">{{ $result->phone }}</td>
                                    <td data-label="Lahir">{{ $result->birth_date->format('d/m/Y') }}</td>
                                    <td data-label="Aksi" class="table-actions">
                                        @if ($result->is_active)
                                            <a class="btn btn-sm btn-primary" href="{{ route('admin.appointments.create', array_filter(['pasien' => $result->id, 'kembali' => $kembali])) }}" aria-label="Pilih {{ $result->full_name }} ({{ $result->patient_number ?? 'tanpa nomor' }})">Pilih</a>
                                        @else
                                            <span class="badge text-bg-secondary">Profil nonaktif</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        @can('create', \App\Models\PatientProfile::class)
            <p class="mb-3">
                Tidak ditemukan?
                <a href="{{ route('admin.patients.create', ['kembali' => $returnPath]) }}" class="btn btn-sm btn-outline-primary">Buat profil baru</a>
                <span class="small text-secondary">(lalu kembali ke formulir ini)</span>
            </p>
        @endcan

        @if ($profile !== null)
            <div class="alert alert-secondary mb-0" role="status">
                Terpilih: <strong>{{ $profile->full_name }}</strong> ({{ $profile->patient_number ?? '-' }}), lahir {{ $profile->birth_date->format('d/m/Y') }}@if ($profile->masked_bpjs), BPJS {{ $profile->masked_bpjs }}@endif
                <a href="{{ route('admin.appointments.create', array_filter(['kembali' => $kembali])) }}" class="ms-2">Ganti</a>
            </div>
        @endif
    </div>
</section>
