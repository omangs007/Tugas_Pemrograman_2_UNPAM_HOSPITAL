{{-- Modal konfirmasi status sesi (tutup, buka, batalkan); hanya untuk pemegang schedules.manage. Satu modal dipakai
     ulang oleh semua baris lewat data-confirm-action. Alasan wajib hanya untuk membatalkan (V-24, minimal 5 karakter). --}}
@can('create', \App\Models\PracticeSession::class)
    <x-confirm-modal id="modal-sesi-tutup" title="Tutup sesi?" message="Sesi akan ditutup untuk pendaftaran baru." confirm-label="Ya, tutup" variant="primary" method="PATCH">
        <input type="hidden" name="status" value="closed">
        <p class="small text-secondary mb-0">Pendaftaran yang sudah ada tetap berlaku.</p>
    </x-confirm-modal>

    <x-confirm-modal id="modal-sesi-buka" title="Buka sesi?" message="Sesi akan dibuka kembali untuk pendaftaran." confirm-label="Ya, buka" variant="primary" method="PATCH">
        <input type="hidden" name="status" value="open">
    </x-confirm-modal>

    <x-confirm-modal id="modal-sesi-batalkan" title="Batalkan sesi?" message="Sesi akan dibatalkan." confirm-label="Ya, batalkan sesi" variant="danger" method="PATCH" :reason-required="true">
        <input type="hidden" name="status" value="cancelled">
        <p class="small text-secondary">Alasan wajib diisi, minimal 5 karakter. Bila sesi sudah memiliki pendaftaran aktif, pembatalan dikirim ke layar Sesi terdampak untuk diselesaikan petugas pendaftaran.</p>
    </x-confirm-modal>

    <x-confirm-modal id="modal-sesi-hapus" title="Hapus sesi tambahan?" message="Sesi tambahan akan dihapus." confirm-label="Ya, hapus" variant="danger" method="DELETE" />
@endcan
