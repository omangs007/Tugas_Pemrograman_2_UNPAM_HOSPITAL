{{-- Variabel: $schedule (DoctorSchedule|null), $action (URL), $submitLabel, $doctorOptions, $clinicOptions, $dayOptions.
     Saat ubah, dokter tidak dapat diganti (buat templat baru bila perlu); kolom dokter hanya tampil sebagai teks. --}}
@php($isEdit = $schedule !== null)
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
                        <div class="mb-3">
                            <span class="form-label fw-semibold d-block" id="doctor_id">Dokter</span>
                            <p class="form-control-plaintext mb-0">{{ $schedule->doctor->display_name }}</p>
                            <input type="hidden" name="doctor_id" value="{{ $schedule->doctor_id }}">
                            <div class="form-text">Dokter pada templat tidak dapat diganti. Buat templat baru, lalu nonaktifkan templat lama.</div>
                        </div>
                    @else
                        <x-form.select name="doctor_id" label="Dokter" :options="$doctorOptions" :selected="null" placeholder="Pilih dokter" :required="true" help="Dokter yang diarsipkan tidak dapat diberi jadwal. Dokter nonaktif boleh diberi templat, tetapi sesinya baru dibangkitkan setelah dokter aktif." />
                    @endif

                    <x-form.select name="clinic_id" label="Poliklinik" :options="$clinicOptions" :selected="$schedule?->clinic_id" placeholder="Pilih poliklinik" :required="true" help="Hanya poliklinik aktif yang dapat didaftar. Poliklinik tidak dapat diganti bila masih ada sesi mendatang dengan pendaftaran aktif." />

                    <x-form.select name="day_of_week" label="Hari praktik" :options="$dayOptions" :selected="$schedule?->day_of_week" placeholder="Pilih hari" :required="true" />

                    <div class="row">
                        <div class="col-6">
                            <x-form.input name="start_time" type="time" label="Jam mulai" :value="$schedule ? substr((string) $schedule->start_time, 0, 5) : null" :required="true" />
                        </div>
                        <div class="col-6">
                            <x-form.input name="end_time" type="time" label="Jam selesai" :value="$schedule ? substr((string) $schedule->end_time, 0, 5) : null" :required="true" />
                        </div>
                    </div>

                    <x-form.input name="quota" type="number" label="Kuota per sesi" :value="$schedule?->quota ?? 20" inputmode="numeric" min="1" max="200" :required="true" help="Angka bulat 1 sampai 200." />
                </div>

                <div class="col-12 col-md-6">
                    <x-form.input name="valid_from" type="date" label="Berlaku dari" :value="$schedule?->valid_from?->toDateString() ?? now()->toDateString()" :required="true" help="Templat baru tidak boleh berlaku mulai tanggal yang sudah lewat." />
                    <x-form.input name="valid_until" type="date" label="Berlaku sampai (opsional)" :value="$schedule?->valid_until?->toDateString()" help="Kosongkan bila berlaku tanpa batas akhir." />
                    <x-form.input name="notes" label="Catatan (opsional)" :value="$schedule?->notes" maxlength="255" help="Catatan singkat, maksimal 255 karakter." />
                    <x-form.checkbox name="is_active" label="Templat aktif" :checked="$schedule?->is_active ?? true" :with-hidden="true" help="Templat nonaktif tidak membangkitkan sesi. Menonaktifkan templat memengaruhi sesi mendatang yang berasal darinya." />
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('admin.schedules.index') }}" class="btn btn-outline-secondary">Batal</a>
            <x-loading-button icon="bi-check-lg" loading-text="Menyimpan dan membangkitkan sesi…">{{ $submitLabel }}</x-loading-button>
        </div>
    </div>
</form>
