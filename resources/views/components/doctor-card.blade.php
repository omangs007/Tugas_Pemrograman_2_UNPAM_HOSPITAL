@props(['doctor', 'headingLevel' => 3, 'showClinic' => false])
@php
    $level = min(6, max(2, (int) $headingLevel));
    $heading = 'h'.$level;
    $specialty = $doctor->primary_specialty;
    $url = \Illuminate\Support\Facades\Route::has('doctors.show') ? route('doctors.show', $doctor) : null;
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
        </div>
    </div>
</article>
