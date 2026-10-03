<?php

namespace Tests\Feature\View;

use Tests\TestCase;

class ConfirmModalComponentTest extends TestCase
{
    public function test_modal_has_accessible_structure_and_script_hooks(): void
    {
        $this->blade('<x-confirm-modal id="hapus-dokter" title="Hapus dokter?" message="Data dokter akan dihapus." action="/admin/dokter/1" method="DELETE" variant="danger" confirm-label="Ya, hapus" />')
            ->assertSee('class="modal fade"', false)
            ->assertSee('id="hapus-dokter"', false)
            ->assertSee('tabindex="-1"', false)
            ->assertSee('aria-labelledby="hapus-dokter-title"', false)
            ->assertSee('data-confirm-modal', false)
            ->assertSee('<h2 class="modal-title h5" id="hapus-dokter-title">Hapus dokter?</h2>', false)
            ->assertSee('<p data-confirm-text>Data dokter akan dihapus.</p>', false)
            ->assertSee('<form method="POST" action="/admin/dokter/1" data-confirm-form>', false)
            ->assertSee('name="_token"', false)
            ->assertSee('<input type="hidden" name="_method" value="DELETE">', false)
            ->assertSee('data-bs-dismiss="modal"', false)
            ->assertSee('aria-label="Tutup"', false)
            ->assertSee('Batal')
            ->assertSee('class="btn btn-danger"', false)
            ->assertSee('<span data-loading-label>Ya, hapus</span>', false)
            ->assertDontSee('name="reason"', false);
    }

    public function test_modal_defaults(): void
    {
        $this->blade('<x-confirm-modal id="konfirmasi" title="Lanjutkan?" />')
            ->assertSee('<form method="POST" action="#" data-confirm-form>', false)
            ->assertSee('class="btn btn-primary"', false)
            ->assertSee('<span data-loading-label>Ya, lanjutkan</span>', false)
            ->assertSee('Batal')
            ->assertSee('name="_token"', false)
            ->assertDontSee('name="_method"', false);
    }

    public function test_modal_supports_patch_and_falls_back_for_unknown_method_and_variant(): void
    {
        $this->blade('<x-confirm-modal id="a" title="Ubah?" method="PATCH" />')
            ->assertSee('<input type="hidden" name="_method" value="PATCH">', false);

        $this->blade('<x-confirm-modal id="b" title="Ubah?" method="GET" variant="neon" />')
            ->assertDontSee('name="_method"', false)
            ->assertSee('class="btn btn-primary"', false)
            ->assertDontSee('btn-neon', false);
    }

    public function test_modal_with_required_reason_adds_a_labelled_textarea(): void
    {
        $this->blade('<x-confirm-modal id="batalkan" title="Batalkan pendaftaran?" :reason-required="true" />')
            ->assertSee('<label for="batalkan-reason" class="form-label fw-semibold">Alasan <span class="required-mark" aria-hidden="true">*</span></label>', false)
            ->assertSee('<textarea class="form-control" id="batalkan-reason" name="reason" rows="3" required aria-required="true"></textarea>', false);
    }

    public function test_modal_renders_extra_content_slot_and_custom_labels(): void
    {
        $this->blade('<x-confirm-modal id="c" title="Arsipkan?" cancel-label="Jangan" confirm-label="Arsipkan"><p class="text-danger">Peringatan tambahan.</p></x-confirm-modal>')
            ->assertSee('Peringatan tambahan.')
            ->assertSee('Jangan')
            ->assertSee('<span data-loading-label>Arsipkan</span>', false);
    }

    public function test_modal_escapes_title_message_action_and_labels(): void
    {
        $this->blade('<x-confirm-modal id="d" :title="$p" :message="$p" :action="$p" :confirm-label="$p" :cancel-label="$p" />', ['p' => '"><script>alert(1)</script>'])
            ->assertSee('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
