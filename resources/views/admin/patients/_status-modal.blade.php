{{-- Modal status pasien (V-18). Variabel: $patient. Hanya untuk pemegang patients.manage (PatientProfilePolicy::changeStatus).
     Nonaktifkan: alasan wajib 5-255 karakter. Aktifkan kembali: alasan opsional. --}}
@can('changeStatus', $patient)
    @if ($patient->is_active)
        <x-confirm-modal
            id="modal-patient-deactivate"
            title="Nonaktifkan pasien?"
            :message="'Profil '.$patient->full_name.' tidak dapat didaftarkan kunjungan baru selama nonaktif. Riwayat pendaftaran tetap tersimpan.'"
            confirm-label="Ya, nonaktifkan"
            variant="danger"
            method="PATCH"
            :action="route('admin.patients.status', $patient)">
            <input type="hidden" name="is_active" value="0">
            @if ($patient->user_id !== null)
                <p class="small text-body-secondary">Akun login pasien ikut dinonaktifkan dan sesi login yang sedang berjalan diakhiri.</p>
            @endif
            <div class="mb-3">
                <label for="modal-patient-deactivate-reason" class="form-label fw-semibold">Alasan <span class="required-mark" aria-hidden="true">*</span></label>
                <textarea class="form-control" id="modal-patient-deactivate-reason" name="reason" rows="3" minlength="5" maxlength="255" required aria-required="true" aria-describedby="modal-patient-deactivate-reason-help"></textarea>
                <div id="modal-patient-deactivate-reason-help" class="form-text">Wajib diisi, 5 sampai 255 karakter. Penonaktifan ditolak bila pasien masih memiliki pendaftaran aktif.</div>
            </div>
        </x-confirm-modal>
    @else
        <x-confirm-modal
            id="modal-patient-activate"
            title="Aktifkan kembali pasien?"
            :message="'Profil '.$patient->full_name.' dapat didaftarkan kunjungan lagi.'"
            confirm-label="Ya, aktifkan"
            variant="primary"
            method="PATCH"
            :action="route('admin.patients.status', $patient)">
            <input type="hidden" name="is_active" value="1">
            @if ($patient->user_id !== null)
                <p class="small text-body-secondary">Akun login pasien ikut diaktifkan kembali.</p>
            @endif
            <div class="mb-3">
                <label for="modal-patient-activate-reason" class="form-label fw-semibold">Alasan (opsional)</label>
                <textarea class="form-control" id="modal-patient-activate-reason" name="reason" rows="2" maxlength="255"></textarea>
            </div>
        </x-confirm-modal>
    @endif
@endcan
