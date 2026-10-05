@props([
    'sessions',
    'showDoctor' => true,
    'showAction' => true,
    'caption' => 'Jadwal praktik dokter',
    'emptyTitle' => 'Belum ada jadwal praktik',
    'emptyMessage' => null,
])
@php
    // Tombol Daftar hanya bila route pendaftaran ada (fase 6) dan sesi dapat dipesan online (10 §3.3, F3-R25).
    $canRegister = \Illuminate\Support\Facades\Route::has('patient.appointments.create');
    $doctorRoute = \Illuminate\Support\Facades\Route::has('doctors.show');
    $withoutQuota = [\App\Enums\Availability::Leave, \App\Enums\Availability::Holiday, \App\Enums\Availability::Cancelled];
@endphp
@if ($sessions->isEmpty())
    <x-empty-state :title="$emptyTitle" :message="$emptyMessage" icon="bi-calendar-x" />
@else
    <div class="table-responsive">
        <table class="table table-hover align-middle table-cards schedule-table bg-white">
            <caption class="visually-hidden">{{ $caption }}</caption>
            <thead class="table-light">
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Jam</th>
                    @if ($showDoctor)
                        <th scope="col">Dokter</th>
                    @endif
                    <th scope="col">Poliklinik</th>
                    <th scope="col">Sisa kuota</th>
                    <th scope="col">Status</th>
                    @if ($showAction)
                        <th scope="col">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($sessions as $item)
                    @php
                        $session = $item->session;
                        $doctor = $session->doctor;
                        // Hanya spesialisasi aktif yang tampil di publik (D-56); utama lebih dulu.
                        $specialty = $doctor->specialties->filter(fn ($item) => $item->is_active)->sortByDesc(fn ($item) => (int) $item->pivot->is_primary)->first();
                        $date = $session->session_date;
                    @endphp
                    <tr>
                        <th scope="row" class="fw-semibold"><time datetime="{{ $date->toDateString() }}">{{ $date->translatedFormat('D, j M Y') }}</time></th>
                        <td data-label="Jam">{{ $session->time_range }}</td>
                        @if ($showDoctor)
                            <td data-label="Dokter">
                                @if ($doctorRoute)
                                    <a href="{{ route('doctors.show', $doctor) }}">{{ $doctor->display_name }}</a>
                                @else
                                    {{ $doctor->display_name }}
                                @endif
                                @if ($specialty)
                                    <span class="d-block small text-body-secondary">{{ $specialty->name }}</span>
                                @endif
                            </td>
                        @endif
                        <td data-label="Poliklinik">{{ $session->clinic->name }}</td>
                        <td data-label="Sisa kuota">
                            @if (in_array($item->availability, $withoutQuota, true))
                                <span aria-hidden="true">–</span><span class="visually-hidden">tidak berlaku</span>
                            @else
                                {{ $item->remaining }} dari {{ $session->quota }}
                            @endif
                        </td>
                        <td data-label="Status"><x-availability-badge :availability="$item->availability" :remaining="$item->remaining" /></td>
                        @if ($showAction)
                            <td data-label="Aksi" class="table-actions">
                                @if ($canRegister && $item->isBookableOnline)
                                    <a class="btn btn-primary btn-sm" href="{{ route('patient.appointments.create', ['sesi' => $session->id]) }}" aria-label="Daftar ke {{ $doctor->display_name }}, {{ $date->translatedFormat('l, j F Y') }}, {{ str_replace('–', ' sampai ', $session->time_range) }}">Daftar</a>
                                @else
                                    <span class="small text-body-secondary">{{ $item->availability->label() }}</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
