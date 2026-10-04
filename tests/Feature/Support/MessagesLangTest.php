<?php

namespace Tests\Feature\Support;

use Carbon\Carbon;
use Tests\TestCase;

class MessagesLangTest extends TestCase
{
    public function test_standard_flash_messages_are_complete_indonesian_sentences(): void
    {
        $doctor = __('messages.entities.doctor');

        $this->assertSame('Dokter', $doctor);
        $this->assertSame('Dokter berhasil ditambahkan.', __('messages.created', ['entity' => $doctor]));
        $this->assertSame('Spesialisasi berhasil diperbarui.', __('messages.updated', ['entity' => __('messages.entities.specialty')]));
        $this->assertSame('Poliklinik berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.clinic')]));
        $this->assertSame('Gambar berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.media')]));
    }

    public function test_delete_blocked_passes_the_reason_through(): void
    {
        $this->assertSame('Alasan lengkap.', __('messages.delete_blocked', ['reason' => 'Alasan lengkap.']));
    }

    public function test_audit_descriptions(): void
    {
        $entity = __('messages.entities.specialty');

        $this->assertSame('Spesialisasi "Anak" ditambahkan.', __('messages.audit.created', ['entity' => $entity, 'name' => 'Anak']));
        $this->assertSame('Spesialisasi "Anak" diperbarui.', __('messages.audit.updated', ['entity' => $entity, 'name' => 'Anak']));
        $this->assertSame('Spesialisasi "Anak" dihapus.', __('messages.audit.deleted', ['entity' => $entity, 'name' => 'Anak']));
    }

    public function test_every_key_is_translated_and_not_returned_as_a_key(): void
    {
        $keys = ['created', 'updated', 'deleted', 'delete_blocked', 'entities.specialty', 'entities.clinic', 'entities.doctor', 'entities.media', 'audit.created', 'audit.updated', 'audit.deleted'];

        foreach ($keys as $key) {
            $this->assertNotSame("messages.{$key}", __("messages.{$key}"), $key);
        }
    }

    public function test_dates_are_formatted_in_indonesian_without_extra_setup(): void
    {
        $date = Carbon::create(2026, 10, 4, 9, 0, 0, 'Asia/Jakarta');

        $this->assertSame('id', Carbon::getLocale());
        $this->assertSame('Minggu, 4 Oktober 2026', $date->translatedFormat('l, j F Y'));
        $this->assertSame('Senin, 5 Oktober 2026', now()->setDate(2026, 10, 5)->translatedFormat('l, j F Y'));
    }
}
