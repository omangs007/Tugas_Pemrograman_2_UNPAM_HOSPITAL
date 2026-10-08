<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <h1 class="h3 mb-0" id="judul-langkah" tabindex="-1" data-wizard-heading>Langkah 2 - Pilih dokter</h1>
    <p class="mb-0">Poliklinik: <strong>{{ $clinic->name }}</strong> <a href="{{ route('patient.appointments.create') }}" aria-label="Ubah poliklinik">Ubah</a></p>
</div>

@if ($doctorRows->isEmpty())
    <x-empty-state icon="bi-person-x" title="Belum ada dokter yang dapat dipilih" message="Poliklinik ini belum memiliki dokter yang tersedia untuk pendaftaran online.">
        <a class="btn btn-outline-primary" href="{{ route('patient.appointments.create') }}">Pilih poliklinik lain</a>
    </x-empty-state>
@else
    <div class="table-responsive">
        <table class="table align-middle">
            <caption class="visually-hidden">Daftar dokter {{ $clinic->name }}</caption>
            <thead>
                <tr>
                    <th scope="col">Dokter</th>
                    <th scope="col">Praktik terdekat</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($doctorRows as $row)
                    @php
                        $doctor = $row['doctor'];
                        $specialty = $doctor->primary_specialty;
                        $shown = $row['bookable'] ?? $row['next'];
                    @endphp
                    <tr>
                        <th scope="row" class="fw-normal">
                            <div class="d-flex align-items-center gap-3">
                                <div class="doctor-card-photo flex-shrink-0">
                                    <x-image type="doctor" :media="$doctor->firstMedia('photo')" :alt="'Foto '.$doctor->display_name" variant="thumb" ratio="1x1" />
                                </div>
                                <div>
                                    <span class="fw-semibold d-block">{{ $doctor->display_name }}</span>
                                    @if ($specialty)
                                        <span class="badge text-bg-light border">{{ $specialty->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </th>
                        <td>
                            @if ($shown !== null)
                                <time datetime="{{ $shown->session->session_date->toDateString() }}">{{ $shown->session->session_date->translatedFormat('D, j M Y') }}</time> {{ $shown->session->time_range }}
                                @if ($row['bookable'] !== null)
                                    <span class="d-block small text-body-secondary">Sisa {{ $shown->remaining }} dari {{ $shown->session->quota }}</span>
                                @else
                                    <span class="d-block small"><x-availability-badge :availability="$shown->availability" /></span>
                                @endif
                            @else
                                <span class="text-body-secondary">Belum ada jadwal dalam {{ $windowDays }} hari ke depan</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($row['bookable'] !== null)
                                <a class="btn btn-primary btn-sm" href="{{ route('patient.appointments.create', ['poli' => $clinic->slug, 'dokter' => $doctor->slug]) }}" aria-label="Pilih {{ $doctor->display_name }}">Pilih</a>
                            @else
                                <span class="text-body-secondary">(tidak tersedia)</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
