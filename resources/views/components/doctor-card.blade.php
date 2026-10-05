@props(['doctor', 'headingLevel' => 3, 'showClinic' => false, 'nextSession' => null])
@php
    $level = min(6, max(2, (int) $headingLevel));
    $heading = 'h'.$level;
    $specialty = $doctor->primary_specialty;
    $url = \Illuminate\Support\Facades\Route::has('doctors.show') ? route('doctors.show', $doctor) : null;
    // Tombol Daftar hanya bila route pendaftaran ada (fase 6) dan sesi terdekat dapat dipesan online (F3-R25).
    $registerUrl = $nextSession !== null && $nextSession->isBookableOnline && \Illuminate\Support\Facades\Route::has('patient.appointments.create')
        ? route('patient.appointments.create', ['sesi' => $nextSession->session->id])
        : null;
@endphp
<article {{ $attributes->class(['card', 'h-100', 'doctor-card']) }}>
    <div class="card-body d-flex align-items-center gap-3">
        <div class="doctor-card-photo flex-shrink-0">
            <x-image type="doctor" :media="$doctor->firstMedia('photo')" :alt="'Foto '.$doctor->display_name" variant="thumb" ratio="1x1" />
        </div>
        <div class="flex-grow-1">
            <{{ $heading }} class="h6 mb-1">
                @if ($url)
                    <a class="stretched-link text-decoration-none" href="{{ $url }}">{{ $doctor->display_name }}</a>
                @else
                    {{ $doctor->display_name }}
                @endif
            </{{ $heading }}>
            @if ($specialty)
                <span class="badge text-bg-light border">{{ $specialty->name }}</span>
            @endif
            @if ($showClinic)
                <p class="small text-body-secondary mb-0 mt-1">{{ $doctor->clinic->name }}</p>
            @endif
            @if ($nextSession !== null)
                <p class="small mb-0 mt-2"><x-availability-badge :availability="$nextSession->availability" :remaining="$nextSession->remaining" /></p>
                <p class="small text-body-secondary mb-0 mt-1">Jadwal terdekat: <time datetime="{{ $nextSession->session->session_date->toDateString() }}">{{ $nextSession->session->session_date->translatedFormat('l, j F Y') }}</time> · {{ $nextSession->session->time_range }}</p>
                @if ($registerUrl)
                    <a class="btn btn-primary btn-sm position-relative z-2 mt-2" href="{{ $registerUrl }}" aria-label="Daftar ke {{ $doctor->display_name }}, {{ $nextSession->session->session_date->translatedFormat('l, j F Y') }}, {{ str_replace('–', ' sampai ', $nextSession->session->time_range) }}">Daftar</a>
                @endif
            @endif
        </div>
    </div>
</article>
