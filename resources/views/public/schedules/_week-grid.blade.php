@php
    // Grid mingguan (F3-R24): baris = dokter, kolom = Senin-Minggu. Tombol Daftar hanya bila route pendaftaran
    // ada (fase 6) dan sesi dapat dipesan online; sel lain berupa teks status.
    $canRegister = \Illuminate\Support\Facades\Route::has('patient.appointments.create');
@endphp
<div class="table-responsive d-none d-md-block" data-week-grid>
    <table class="table table-bordered align-middle bg-white week-grid">
        <caption class="visually-hidden">Jadwal praktik dokter {{ $weekLabel }}</caption>
        <thead class="table-light">
            <tr>
                <th scope="col">Dokter</th>
                @foreach ($days as $day)
                    <th scope="col" class="text-center"><time datetime="{{ $day->toDateString() }}">{{ $day->translatedFormat('D j') }}</time></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php
                    $doctor = $row['doctor'];
                    // Hanya spesialisasi aktif yang tampil di publik (D-56); utama lebih dulu.
                    $specialty = $doctor->specialties->filter(fn ($item) => $item->is_active)->sortByDesc(fn ($item) => (int) $item->pivot->is_primary)->first();
                @endphp
                <tr>
                    <th scope="row">
                        <a href="{{ route('doctors.show', $doctor) }}">{{ $doctor->display_name }}</a>
                        @if ($specialty)
                            <span class="d-block small text-body-secondary">{{ $specialty->name }}</span>
                        @endif
                    </th>
                    @foreach ($days as $day)
                        @php $cell = $row['cells']->get($day->toDateString()); @endphp
                        <td>
                            @if ($cell === null)
                                <span aria-hidden="true">–</span><span class="visually-hidden">tidak ada praktik</span>
                            @else
                                @foreach ($cell as $item)
                                    @php $session = $item->session; @endphp
                                    @if ($canRegister && $item->isBookableOnline)
                                        <a class="week-cell-item week-cell-link" href="{{ route('patient.appointments.create', ['sesi' => $session->id]) }}" aria-label="Daftar ke {{ $doctor->display_name }}, {{ $day->translatedFormat('l, j F Y') }}, {{ str_replace('–', ' sampai ', $session->time_range) }}, sisa {{ $item->remaining }}">
                                            <span class="d-block small">{{ $session->time_range }}</span>
                                            <x-availability-badge :availability="$item->availability" :remaining="$item->remaining" :compact="true" />
                                        </a>
                                    @else
                                        <span class="week-cell-item">
                                            <span class="d-block small">{{ $session->time_range }}</span>
                                            <x-availability-badge :availability="$item->availability" :remaining="$item->remaining" :compact="true" />
                                        </span>
                                    @endif
                                @endforeach
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
