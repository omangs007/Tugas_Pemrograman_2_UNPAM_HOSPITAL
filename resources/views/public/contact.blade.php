@inject('site', 'App\Services\Settings\SettingsService')
@php
    $address = $site->string('contact.address');
    $phone = $site->string('contact.phone');
    $emergency = $site->string('contact.emergency_phone');
    $email = $site->string('contact.email');
    $hours = $site->string('contact.operational_hours');
    $whatsappUrl = $site->whatsappUrl();
    $directionsUrl = $site->string('map.directions_url');
    $directionsUrl = preg_match('#^https?://#i', $directionsUrl) === 1 ? $directionsUrl : '';
    $telHref = function (string $number): ?string {
        $digits = preg_replace('/[^0-9+]/', '', $number);

        return $digits === '' || $digits === null ? null : 'tel:'.$digits;
    };
    $phoneHref = $phone !== '' ? $telHref($phone) : null;
    $emergencyHref = $emergency !== '' ? $telHref($emergency) : null;
    $consentError = isset($errors) ? (string) $errors->first('consent') : '';
@endphp
<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Kontak dan Lokasi']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('contact')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    <div class="row g-4 g-lg-5">
        <div class="col-lg-6">
            <section class="card mb-4" aria-labelledby="judul-alamat">
                <div class="card-body">
                    <h2 id="judul-alamat" class="h5 mb-3">Alamat</h2>
                    <address class="mb-3">{{ $address !== '' ? $address : 'Alamat belum diisi.' }}</address>

                    @if ($phone !== '' || $emergency !== '' || $whatsappUrl !== null || $email !== '' || $hours !== '')
                        <ul class="list-unstyled mb-3">
                            @if ($phone !== '')
                                <li>Telepon: @if ($phoneHref !== null)<a href="{{ $phoneHref }}">{{ $phone }}</a>@else{{ $phone }}@endif</li>
                            @endif
                            @if ($emergency !== '')
                                <li>IGD: @if ($emergencyHref !== null)<a href="{{ $emergencyHref }}">{{ $emergency }}</a>@else{{ $emergency }}@endif</li>
                            @endif
                            @if ($whatsappUrl !== null)
                                <li>WhatsApp: {{ $site->string('contact.whatsapp') }}</li>
                            @endif
                            @if ($email !== '')
                                <li>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></li>
                            @endif
                            @if ($hours !== '')
                                <li>Jam operasional: {{ $hours }}</li>
                            @endif
                        </ul>
                    @endif

                    @if ($directionsUrl !== '' || $whatsappUrl !== null)
                        <div class="d-grid d-sm-flex flex-wrap gap-2">
                            @if ($directionsUrl !== '')
                                <a class="btn btn-primary" href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>
                            @endif
                            @if ($whatsappUrl !== null)
                                <a class="btn btn-outline-primary" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> Chat via WhatsApp (membuka WhatsApp)</a>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            <section class="card" aria-labelledby="judul-peta">
                <div class="card-body">
                    <h2 id="judul-peta" class="h5 mb-3">Peta</h2>
                    <x-map-embed />
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card" aria-labelledby="judul-form-kontak">
                <div class="card-body">
                    <h2 id="judul-form-kontak" class="h5 mb-3">Kirim Pesan</h2>

                    <x-form.errors-summary />

                    <form method="POST" action="{{ route('contact.store') }}" novalidate>
                        @csrf
                        <p class="small text-body-secondary">Kolom bertanda <span class="required-mark" aria-hidden="true">*</span> wajib diisi.</p>

                        <x-form.input name="name" label="Nama" :required="true" autocomplete="name" maxlength="100" />
                        <x-form.input name="email" type="email" label="Email" :required="true" autocomplete="email" maxlength="255" />
                        <x-form.input name="phone" type="tel" label="Telepon (opsional)" inputmode="tel" autocomplete="tel" maxlength="25" help="Nomor seluler Indonesia, mis. 081234567890." />
                        <x-form.input name="subject" label="Subjek" :required="true" maxlength="150" />
                        <x-form.textarea name="message" label="Pesan" :required="true" :rows="6" maxlength="2000" help="Jangan menuliskan data medis atau nomor identitas pada pesan ini." />

                        <div class="d-none" aria-hidden="true">
                            <label for="website">Situs web</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" id="consent" name="consent" value="1" @checked(old('consent') === '1') aria-required="true" @if ($consentError !== '') aria-invalid="true" aria-describedby="consent-error" @endif @class(['form-check-input', 'is-invalid' => $consentError !== ''])>
                                <label class="form-check-label" for="consent">Saya menyetujui data saya diproses sesuai @if (Route::has('privacy'))<a href="{{ route('privacy') }}">Kebijakan Privasi</a>.@else Kebijakan Privasi.@endif <span class="required-mark" aria-hidden="true">*</span></label>
                            </div>
                            @if ($consentError !== '')
                                <div id="consent-error" class="invalid-feedback d-block">{{ $consentError }}</div>
                            @endif
                        </div>

                        <x-loading-button loading-text="Mengirim…" icon="bi-send">Kirim Pesan</x-loading-button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts.public>
