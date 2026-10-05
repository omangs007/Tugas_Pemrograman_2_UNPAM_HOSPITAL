{{-- Variabel: $session (PracticeSession|null), $action (URL), $submitLabel, $doctorOptions, $clinicOptions.
     Tambah: dokter aktif, poliklinik, tanggal, jam, kuota. Ubah: hanya jam dan kuota; dokter, poliklinik, dan tanggal
     tampil sebagai teks (09 §3.7). Hanya jumlah terisi yang ditampilkan, tanpa data pasien (F3-R30). --}}
@php($isEdit = $session !== null)
<form method="POST" action="{{ $action }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-md-6">
                    @if ($isEdit)
                        <dl class="row mb-3">
                            <dt class="col-sm-4">Dokter</dt>
                            <dd class="col-sm-8">{{ $session->doctor->display_name }}</dd>
                            <dt class="col-sm-4">Poliklinik</dt>
                            <dd class="col-sm-8">{{ $session->clinic->name }}</dd>
                            <dt class="col-sm-4">Tanggal</dt>
                            <dd class="col-sm-8">{{ $session->session_date->translatedFormat('l, j F Y') }}</dd>
                            <dt class="col-sm-4">Terisi</dt>
                            <dd class="col-sm-8">{{ $session->booked_count }} dari {{ $session->quota }}</dd>
                        </dl>
                    @else
                        <x-form.select name="doctor_id" label="Dokter" :options="$doctorOptions" :selected="null" placeholder="Pilih dokter" :required="true" help="Hanya dokter aktif yang dapat diberi sesi tambahan." />
                        <x-form.select name="clinic_id" label="Poliklinik" :options="$clinicOptions" :selected="null" placeholder="Pilih poliklinik" :required="true" help="Hanya poliklinik aktif yang dapat didaftar." />
                        <x-form.input name="session_date" type="date" label="Tanggal sesi" :value="now()->addDay()->toDateString()" :required="true" help="Mulai hari ini sampai 365 hari ke depan, tidak pada tanggal yang tercakup pengecualian." />
                    @endif
                </div>

                <div class="col-12 col-md-6">
                    <div class="row">
                        <div class="col-6">
                            <x-form.input name="start_time" type="time" label="Jam mulai" :value="$session ? substr((string) $session->start_time, 0, 5) : null" :required="true" />
                        </div>
                        <div class="col-6">
                            <x-form.input name="end_time" type="time" label="Jam selesai" :value="$session ? substr((string) $session->end_time, 0, 5) : null" :required="true" />
                        </div>
                    </div>
                    <x-form.input name="quota" type="number" label="Kuota" :value="$session?->quota ?? 20" inputmode="numeric" min="1" max="200" :required="true" :help="$isEdit ? 'Angka bulat 1 sampai 200, tidak boleh kurang dari jumlah terisi ('.$session->booked_count.').' : 'Angka bulat 1 sampai 200.'" />
                    @if ($isEdit)
                        <div class="alert alert-info small mb-0" role="note">
                            Mengubah kuota langsung berlaku. Bila jam digeser pada sesi yang sudah memiliki pendaftaran aktif, perubahan dikirim ke layar Sesi terdampak untuk diselesaikan petugas pendaftaran, dan jam lama tetap berlaku sampai saat itu.
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('admin.sessions.index') }}" class="btn btn-outline-secondary">Batal</a>
            <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
        </div>
    </div>
</form>
