<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

/**
 * Kunci pesan baku fase 3 (F3 bagian D): `messages.entities.schedule|exception|session`.
 */
class ScheduleMessagesTest extends TestCase
{
    public function test_entity_names_are_translated_and_not_returned_as_keys(): void
    {
        $this->assertSame('Templat jadwal', __('messages.entities.schedule'));
        $this->assertSame('Pengecualian jadwal', __('messages.entities.exception'));
        $this->assertSame('Sesi praktik', __('messages.entities.session'));
    }

    public function test_standard_flash_messages_compose_with_the_new_entities(): void
    {
        $this->assertSame('Templat jadwal berhasil ditambahkan.', __('messages.created', ['entity' => __('messages.entities.schedule')]));
        $this->assertSame('Pengecualian jadwal berhasil diperbarui.', __('messages.updated', ['entity' => __('messages.entities.exception')]));
        $this->assertSame('Sesi praktik berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.session')]));
    }

    public function test_existing_entity_names_are_unchanged(): void
    {
        $this->assertSame('Spesialisasi', __('messages.entities.specialty'));
        $this->assertSame('Poliklinik', __('messages.entities.clinic'));
        $this->assertSame('Dokter', __('messages.entities.doctor'));
        $this->assertSame('Gambar', __('messages.entities.media'));
    }
}
