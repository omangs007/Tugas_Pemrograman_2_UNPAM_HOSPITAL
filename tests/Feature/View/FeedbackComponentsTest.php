<?php

namespace Tests\Feature\View;

use Tests\TestCase;

class FeedbackComponentsTest extends TestCase
{
    public function test_flash_alert_renders_nothing_without_flash_messages(): void
    {
        $this->blade('<x-flash-alert />')
            ->assertDontSee('alert', false);
    }

    public function test_flash_alert_success_is_green_with_status_role(): void
    {
        session()->flash('success', 'Dokter berhasil ditambahkan.');

        $this->blade('<x-flash-alert />')
            ->assertSee('alert alert-success', false)
            ->assertSee('role="status"', false)
            ->assertSee('<div class="flex-grow-1">Dokter berhasil ditambahkan.</div>', false)
            ->assertSee('data-bs-dismiss="alert"', false)
            ->assertSee('aria-label="Tutup"', false)
            ->assertDontSee('role="alert"', false);
    }

    public function test_flash_alert_error_and_warning_use_alert_role(): void
    {
        session()->flash('error', 'Terlalu banyak percobaan.');
        session()->flash('warning', '3 sesi perlu ditindaklanjuti.');

        $this->blade('<x-flash-alert />')
            ->assertSee('alert alert-danger', false)
            ->assertSee('alert alert-warning', false)
            ->assertSee('role="alert"', false)
            ->assertSeeInOrder(['Terlalu banyak percobaan.', '3 sesi perlu ditindaklanjuti.'])
            ->assertDontSee('role="status"', false);
    }

    public function test_flash_alert_only_renders_requested_keys(): void
    {
        session()->flash('success', 'Berhasil.');
        session()->flash('error', 'Gagal.');

        $this->blade('<x-flash-alert :keys="[\'error\']" />')
            ->assertSee('Gagal.')
            ->assertDontSee('Berhasil.');
    }

    public function test_flash_alert_ignores_unknown_keys_and_non_string_values(): void
    {
        session()->flash('info', 'Tidak dikenal.');
        session()->flash('success', ['bukan', 'string']);

        $this->blade('<x-flash-alert :keys="[\'info\', \'success\']" />')
            ->assertDontSee('Tidak dikenal.')
            ->assertDontSee('alert alert-success', false);
    }

    public function test_flash_alert_escapes_messages(): void
    {
        session()->flash('error', '<script>alert(1)</script>');

        $this->blade('<x-flash-alert />')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_loading_button_exposes_attributes_used_by_app_js(): void
    {
        $this->blade('<x-loading-button>Simpan</x-loading-button>')
            ->assertSee('type="submit"', false)
            ->assertSee('class="btn btn-primary"', false)
            ->assertSee('data-loading-button', false)
            ->assertSee('data-loading-text="Memproses…"', false)
            ->assertSee('class="spinner-border spinner-border-sm d-none"', false)
            ->assertSee('role="status"', false)
            ->assertSee('<span class="visually-hidden">Memproses…</span>', false)
            ->assertSee('<span data-loading-label>Simpan</span>', false)
            ->assertDontSee('data-loading-icon', false);
    }

    public function test_loading_button_supports_variant_text_icon_type_and_extra_attributes(): void
    {
        $this->blade('<x-loading-button type="button" variant="danger" loading-text="Menghapus…" icon="bi-trash" class="w-100" id="hapus">Hapus</x-loading-button>')
            ->assertSee('type="button"', false)
            ->assertSee('class="btn btn-danger w-100"', false)
            ->assertSee('id="hapus"', false)
            ->assertSee('data-loading-text="Menghapus…"', false)
            ->assertSee('<i class="bi bi-trash" aria-hidden="true" data-loading-icon></i>', false)
            ->assertSee('<span data-loading-label>Hapus</span>', false);
    }

    public function test_loading_button_escapes_loading_text(): void
    {
        $this->blade('<x-loading-button :loading-text="$text">Kirim</x-loading-button>', ['text' => '"><script>alert(1)</script>'])
            ->assertSee('data-loading-text="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_stat_card_renders_label_value_hint_and_icon(): void
    {
        $this->blade('<x-stat-card label="Dokter Aktif" :value="42" icon="bi-person-badge" variant="success" hint="Per hari ini" />')
            ->assertSee('id="stat-dokter-aktif-label"', false)
            ->assertSee('aria-labelledby="stat-dokter-aktif-label"', false)
            ->assertSee('Dokter Aktif')
            ->assertSee('>42</p>', false)
            ->assertSee('Per hari ini')
            ->assertSee('text-bg-success', false)
            ->assertSee('<i class="bi bi-person-badge" aria-hidden="true"></i>', false)
            ->assertDontSee('stretched-link', false);
    }

    public function test_stat_card_with_href_makes_the_label_a_link(): void
    {
        $this->blade('<x-stat-card label="Pendaftaran Hari Ini" value="7" href="/admin/pendaftaran" />')
            ->assertSee('<a href="/admin/pendaftaran" class="stretched-link text-decoration-none text-reset">Pendaftaran Hari Ini</a>', false)
            ->assertDontSee('text-bg-', false);
    }

    public function test_stat_card_falls_back_to_primary_for_unknown_variant(): void
    {
        $this->blade('<x-stat-card label="Pasien" value="1" icon="bi-people" variant="neon" />')
            ->assertSee('text-bg-primary', false)
            ->assertDontSee('text-bg-neon', false);
    }

    public function test_stat_card_escapes_all_props(): void
    {
        $this->blade('<x-stat-card :label="$p" :value="$p" :hint="$p" href="/x" />', ['p' => '<script>alert(1)</script>'])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
