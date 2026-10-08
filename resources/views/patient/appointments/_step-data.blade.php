@php
    $reviewUrl = Route::has('patient.appointments.review') ? route('patient.appointments.review') : '';
    $bpjsHelp = 'Isi hanya bila memilih BPJS Kesehatan (13 digit angka).'.($storedBpjs ? ' Kosongkan untuk memakai nomor BPJS tersimpan ('.$storedBpjs.').' : '');
@endphp
<h1 class="h3 mb-3" id="judul-langkah" tabindex="-1" data-wizard-heading>Langkah 4 - Data pendaftaran</h1>

<x-form.errors-summary />

<section class="card mb-4" aria-labelledby="judul-ringkasan">
    <div class="card-body">
        <h2 class="h5" id="judul-ringkasan">Sesi yang dipilih</h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Poliklinik</dt>
            <dd class="col-sm-9">{{ $clinic->name }}</dd>
            <dt class="col-sm-3">Dokter</dt>
            <dd class="col-sm-9">{{ $doctor->display_name }}</dd>
            <dt class="col-sm-3">Jadwal</dt>
            <dd class="col-sm-9"><time datetime="{{ $session->session_date->toDateString() }}">{{ $session->session_date->translatedFormat('l, j F Y') }}</time>, {{ $session->time_range }}</dd>
            <dt class="col-sm-3">Pasien</dt>
            <dd class="col-sm-9 mb-0">{{ $profile->full_name }} ({{ $profile->patient_number ?? '-' }}) <a href="{{ route('patient.profile.edit') }}">Ubah profil</a></dd>
        </dl>
    </div>
</section>

<form method="POST" action="{{ $reviewUrl }}" novalidate>
    @csrf
    <input type="hidden" id="practice_session_id" name="practice_session_id" value="{{ $session->id }}">

    <div data-payment-toggle>
        <x-form.radio-group name="payment_type" legend="Jenis pembayaran" :options="\App\Enums\PaymentType::options()" required />

        <div data-payment-block="bpjs">
            <x-form.input name="bpjs_number" label="Nomor BPJS (13 digit)" inputmode="numeric" autocomplete="off" maxlength="20" :help="$bpjsHelp" />
            <x-form.checkbox name="save_bpjs" label="Simpan nomor BPJS ke profil saya" help="Nomor disimpan terenkripsi di profil Anda." />
        </div>

        <div data-payment-block="asuransi">
            <x-form.select name="insurance_partner_id" label="Mitra asuransi" :options="$partners->pluck('name', 'id')->all()" placeholder="Pilih mitra asuransi" help="Isi hanya bila memilih Asuransi." />
            <x-form.input name="policy_number" label="Nomor polis (opsional)" autocomplete="off" maxlength="30" help="Isi hanya bila memilih Asuransi." />
        </div>
    </div>

    <x-form.textarea name="complaint" label="Keluhan singkat (opsional, maks 500 karakter)" rows="3" maxlength="500" />

    <div class="d-flex flex-wrap gap-2 mt-3">
        <a class="btn btn-outline-secondary" href="{{ $links[3] }}">Kembali</a>
        <x-loading-button>Tinjau Pendaftaran</x-loading-button>
    </div>
</form>
