<h1 class="h3 mb-1" id="judul-langkah" tabindex="-1" data-wizard-heading>Langkah 1 - Pilih poliklinik</h1>
<p class="text-body-secondary mb-4">Hanya poliklinik yang aktif dan dapat didaftar online yang ditampilkan.</p>

@if ($clinicRows->isEmpty())
    <x-empty-state icon="bi-hospital" title="Belum ada poliklinik yang dapat didaftar" message="Saat ini belum ada poliklinik yang dibuka untuk pendaftaran online. Silakan hubungi rumah sakit untuk informasi lebih lanjut.">
        @if (Route::has('contact'))
            <a class="btn btn-outline-primary" href="{{ route('contact') }}">Hubungi Kami</a>
        @endif
    </x-empty-state>
@else
    <div class="table-responsive">
        <table class="table align-middle">
            <caption class="visually-hidden">Daftar poliklinik yang dapat didaftar online</caption>
            <thead>
                <tr>
                    <th scope="col">Poliklinik</th>
                    <th scope="col">Lokasi</th>
                    <th scope="col" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clinicRows as $row)
                    @php
                        $clinic = $row['clinic'];
                    @endphp
                    <tr @class(['text-body-secondary' => ! $row['hasSessions']])>
                        <th scope="row" class="fw-semibold">{{ $clinic->name }} <span class="fw-normal">({{ $clinic->code }})</span></th>
                        <td>{{ $clinic->location ?: '-' }}</td>
                        <td class="text-end">
                            @if ($row['hasSessions'])
                                <a class="btn btn-primary btn-sm" href="{{ route('patient.appointments.create', ['poli' => $clinic->slug]) }}" aria-label="Pilih {{ $clinic->name }}">Pilih</a>
                            @else
                                <span>Belum ada jadwal</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
