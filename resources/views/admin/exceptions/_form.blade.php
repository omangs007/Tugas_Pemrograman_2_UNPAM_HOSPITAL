{{-- Variabel: $exception (ScheduleException|null), $action (URL), $submitLabel, $typeOptions, $doctorOptions.
     Validasi kombinasi jenis dan dokter ada di server (12 V-25); tidak ada JavaScript pada form ini. --}}
@php($isEdit = $exception !== null)
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
                    <x-form.select name="type" label="Jenis pengecualian" :options="$typeOptions" :selected="$exception?->type" placeholder="Pilih jenis" :required="true" help="Cuti dokter: dokter wajib dipilih. Libur rumah sakit dan libur nasional: berlaku untuk semua dokter. Lainnya: dokter boleh dipilih atau dikosongkan." />
                    <x-form.select name="doctor_id" label="Dokter" :options="$doctorOptions" :selected="$exception?->doctor_id" help="Pilih dokter untuk cuti dokter. Biarkan pilihan Semua dokter untuk libur rumah sakit atau libur nasional." />
                </div>
                <div class="col-12 col-md-6">
                    <div class="row">
                        <div class="col-6">
                            <x-form.input name="start_date" type="date" label="Tanggal mulai" :value="$exception?->start_date?->toDateString() ?? now()->toDateString()" :required="true" />
                        </div>
                        <div class="col-6">
                            <x-form.input name="end_date" type="date" label="Tanggal selesai" :value="$exception?->end_date?->toDateString() ?? now()->toDateString()" :required="true" />
                        </div>
                    </div>
                    <div class="form-text mb-3">Pengecualian berlaku satu hari penuh untuk setiap tanggal dalam rentang, maksimal 365 hari. Tanggal mulai tidak boleh di masa lalu{{ $isEdit ? ', kecuali tidak diubah' : '' }}.</div>
                    <x-form.input name="reason" label="Alasan" :value="$exception?->reason" :required="true" maxlength="255" help="Tampil sebagai alasan pada sesi yang dibatalkan. Minimal 3 karakter." />
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('admin.exceptions.index') }}" class="btn btn-outline-secondary">Batal</a>
            <x-loading-button icon="bi-check-lg" loading-text="Menyimpan dan memperbarui sesi…">{{ $submitLabel }}</x-loading-button>
        </div>
    </div>
</form>
