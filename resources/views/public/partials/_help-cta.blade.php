@php
    $emergency = $site->string('contact.emergency_phone');
    $phone = $site->string('contact.phone');
    $whatsappUrl = $site->whatsappUrl();
    $directionsUrl = $site->string('map.directions_url');
    $contactUrl = Route::has('contact') ? route('contact') : null;
    $telHref = function (string $number): ?string {
        $digits = preg_replace('/[^0-9+]/', '', $number);

        return $digits === '' || $digits === null ? null : 'tel:'.$digits;
    };
    $emergencyHref = $emergency !== '' ? $telHref($emergency) : null;
    $phoneHref = $phone !== '' ? $telHref($phone) : null;
@endphp
@if ($emergency !== '' || $phone !== '' || $whatsappUrl !== null || $directionsUrl !== '' || $contactUrl !== null)
    <section class="cta-block" aria-labelledby="judul-bantuan">
        <h2 id="judul-bantuan" class="h3 text-white">Butuh bantuan segera?</h2>
        @if ($emergency !== '')
            <p class="mb-3">IGD: @if ($emergencyHref !== null)<a class="text-white fw-bold" href="{{ $emergencyHref }}">{{ $emergency }}</a>@else<span class="fw-bold">{{ $emergency }}</span>@endif</p>
        @endif
        <div class="d-flex flex-wrap gap-2">
            @if ($whatsappUrl !== null)
                <a class="btn btn-light" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp</a>
            @endif
            @if ($phoneHref !== null)
                <a class="btn btn-light" href="{{ $phoneHref }}"><i class="bi bi-telephone" aria-hidden="true"></i> Telepon</a>
            @endif
            @if ($directionsUrl !== '')
                <a class="btn btn-light" href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>
            @endif
            @if ($contactUrl !== null)
                <a class="btn btn-outline-light" href="{{ $contactUrl }}"><i class="bi bi-chat-dots" aria-hidden="true"></i> Kirim Pesan</a>
            @endif
        </div>
    </section>
@endif
