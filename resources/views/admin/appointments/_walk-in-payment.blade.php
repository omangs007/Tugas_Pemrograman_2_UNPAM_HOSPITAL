@php
    $bpjsHelp = 'Isi hanya bila memilih BPJS Kesehatan (13 digit angka).'.($storedBpjs ? ' Kosongkan untuk memakai nomor BPJS tersimpan ('.$storedBpjs.').' : '');
@endphp
<section class="card mb-4" aria-labelledby="judul-pembayaran">
    <div class="card-body">
        <h2 class="h5" id="judul-pembayaran">3. Data Pembayaran</h2>

        @if ($profile === null)
            <p class="text-body-secondary mb-0" aria-disabled="true">Pilih pasien terlebih dahulu.</p>
        @elseif ($chosenItem === null)
            <p class="text-body-secondary mb-0" aria-disabled="true">Pilih sesi terlebih dahulu.</p>
        @else
            @php $chosenSession = $chosenItem->session; @endphp
            <dl class="row">
                <dt class="col-sm-3">Pasien</dt>
                <dd class="col-sm-9">{{ $profile->full_name }} ({{ $profile->patient_number ?? '-' }})</dd>
                <dt class="col-sm-3">Sesi</dt>
                <dd class="col-sm-9">{{ $chosenSession->clinic->name }}, {{ $chosenSession->doctor->display_name }}, <time datetime="{{ $chosenSession->session_date->toDateString() }}">{{ $chosenSession->session_date->translatedFormat('l, j F Y') }}</time>, {{ $chosenSession->time_range }}</dd>
            </dl>

            <form method="POST" action="{{ route('admin.appointments.store') }}" novalidate>
                @csrf
                <input type="hidden" id="patient_profile_id" name="patient_profile_id" value="{{ $profile->id }}">
                <input type="hidden" id="practice_session_id" name="practice_session_id" value="{{ $chosenSession->id }}">

                <div data-payment-toggle>
                    <x-form.radio-group name="payment_type" legend="Jenis pembayaran" :options="\App\Enums\PaymentType::options()" required />

                    <div data-payment-block="bpjs">
                        <x-form.input name="bpjs_number" label="Nomor BPJS (13 digit)" inputmode="numeric" autocomplete="off" maxlength="20" :help="$bpjsHelp" />
                        <x-form.checkbox name="save_bpjs" label="Simpan nomor BPJS ke profil pasien" help="Nomor disimpan terenkripsi di profil pasien." />
                    </div>

                    <div data-payment-block="asuransi">
                        <x-form.select name="insurance_partner_id" label="Mitra asuransi" :options="$partners->pluck('name', 'id')->all()" placeholder="Pilih mitra asuransi" help="Isi hanya bila memilih Asuransi." />
                        <x-form.input name="policy_number" label="Nomor polis (opsional)" autocomplete="off" maxlength="30" help="Isi hanya bila memilih Asuransi." />
                    </div>
                </div>

                <x-form.textarea name="complaint" label="Keluhan singkat (opsional, maks 500 karakter)" rows="3" maxlength="500" />

                <p class="small text-secondary">Kanal: Loket. Status awal: Dikonfirmasi. Duplikat dan benturan jadwal tetap diperiksa.</p>

                <div class="d-flex flex-wrap gap-2">
                    <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">Simpan Pendaftaran</x-loading-button>
                    <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        @endif
    </div>
</section>
