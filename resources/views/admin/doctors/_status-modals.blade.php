{{-- Modal konfirmasi aksi status dokter; hanya untuk pemegang doctors.manage. Alasan bersifat opsional (V-20). --}}
@can('create', \App\Models\Doctor::class)
    <x-confirm-modal id="modal-doctor-deactivate" title="Nonaktifkan dokter?" message="Dokter akan dinonaktifkan." confirm-label="Ya, nonaktifkan" variant="danger" method="PATCH">
        <input type="hidden" name="status" value="inactive">
        <div class="mb-3">
            <label for="modal-doctor-deactivate-reason" class="form-label fw-semibold">Alasan (opsional)</label>
            <textarea class="form-control" id="modal-doctor-deactivate-reason" name="reason" rows="2" maxlength="255"></textarea>
        </div>
    </x-confirm-modal>

    <x-confirm-modal id="modal-doctor-activate" title="Aktifkan dokter?" message="Dokter akan diaktifkan." confirm-label="Ya, aktifkan" variant="primary" method="PATCH">
        <input type="hidden" name="status" value="active">
        <div class="mb-3">
            <label for="modal-doctor-activate-reason" class="form-label fw-semibold">Alasan (opsional)</label>
            <textarea class="form-control" id="modal-doctor-activate-reason" name="reason" rows="2" maxlength="255"></textarea>
        </div>
    </x-confirm-modal>

    <x-confirm-modal id="modal-doctor-restore" title="Pulihkan dokter dari arsip?" message="Dokter akan dipulihkan sebagai nonaktif." confirm-label="Ya, pulihkan" variant="primary" method="PATCH">
        <input type="hidden" name="status" value="inactive">
        <div class="mb-3">
            <label for="modal-doctor-restore-reason" class="form-label fw-semibold">Alasan (opsional)</label>
            <textarea class="form-control" id="modal-doctor-restore-reason" name="reason" rows="2" maxlength="255"></textarea>
        </div>
    </x-confirm-modal>

    <x-confirm-modal id="modal-doctor-archive" title="Arsipkan dokter?" message="Dokter akan diarsipkan." confirm-label="Ya, arsipkan" variant="danger" method="PATCH">
        <div class="mb-3">
            <label for="modal-doctor-archive-reason" class="form-label fw-semibold">Alasan (opsional)</label>
            <textarea class="form-control" id="modal-doctor-archive-reason" name="reason" rows="2" maxlength="255"></textarea>
        </div>
    </x-confirm-modal>
@endcan
