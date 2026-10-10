<x-layouts.admin title="Pendaftaran">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[['label' => 'Pendaftaran']]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        @can('createCounter', \App\Models\Appointment::class)
            <a href="{{ route('admin.appointments.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Pendaftaran Walk-in
            </a>
        @endcan
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="appointments" />
    </div>

    <p class="small text-body-secondary">Bawaan: pendaftaran untuk kunjungan hari ini. Ubah rentang tanggal untuk melihat hari lain.</p>

    <x-data-table
        :rows="$appointments"
        :preserve="[]"
        search-placeholder="Kode booking, no. pasien, atau nama"
        caption="Daftar pendaftaran kunjungan"
        empty-title="Tidak ada pendaftaran"
        empty-message="Tidak ada pendaftaran pada tanggal dan filter yang dipilih.">
        <x-slot:filters>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-dari" class="form-label fw-semibold">Dari</label>
                <input type="date" id="filter-dari" name="dari" value="{{ $range[0]?->toDateString() }}" class="form-control">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-sampai" class="form-label fw-semibold">Sampai</label>
                <input type="date" id="filter-sampai" name="sampai" value="{{ $range[1]?->toDateString() }}" class="form-control">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-poli" class="form-label fw-semibold">Poliklinik</label>
                <select id="filter-poli" name="poli" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($clinicOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected(request()->query('poli') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-dokter" class="form-label fw-semibold">Dokter</label>
                <select id="filter-dokter" name="dokter" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($doctorOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected(request()->query('dokter') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request()->query('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-kanal" class="form-label fw-semibold">Kanal</label>
                <select id="filter-kanal" name="kanal" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($channelOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request()->query('kanal') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        <x-slot:head>
            <th scope="col">Kode booking</th>
            <th scope="col">Kunjungan</th>
            <th scope="col">Poliklinik</th>
            <th scope="col">Dokter</th>
            <th scope="col">Antrean</th>
            <th scope="col">Pasien</th>
            <th scope="col">Pembayaran</th>
            <th scope="col">Status</th>
            <th scope="col">Kanal</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($appointments as $appointment)
            <tr>
                <td data-label="Kode booking"><span class="font-monospace">{{ $appointment->booking_code }}</span></td>
                <td data-label="Kunjungan">
                    <time datetime="{{ $appointment->session_date->toDateString() }}">{{ $appointment->session_date->translatedFormat('l, j M Y') }}</time>
                    <span class="d-block small text-body-secondary">{{ $appointment->practiceSession->time_range }}</span>
                </td>
                <td data-label="Poliklinik">{{ $appointment->clinic->name }}</td>
                <td data-label="Dokter">{{ $appointment->doctor->display_name }}</td>
                <td data-label="Antrean">{{ $appointment->queue_label }}</td>
                <td data-label="Pasien">
                    {{ $appointment->patientProfile->full_name }}
                    <span class="d-block small text-body-secondary">{{ $appointment->patientProfile->patient_number ?? '-' }}</span>
                </td>
                <td data-label="Pembayaran">{{ $appointment->payment_type->label() }}</td>
                <td data-label="Status"><x-status-badge :status="$appointment->status" /></td>
                <td data-label="Kanal"><x-status-badge :status="$appointment->channel" /></td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.appointments.show', ['appointment' => $appointment->booking_code]) }}" class="btn btn-sm btn-outline-secondary" aria-label="Lihat pendaftaran {{ $appointment->booking_code }} atas nama {{ $appointment->patientProfile->full_name }}">Lihat</a>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
