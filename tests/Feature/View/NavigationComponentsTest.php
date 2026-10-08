<?php

namespace Tests\Feature\View;

use Tests\TestCase;

class NavigationComponentsTest extends TestCase
{
    public function test_breadcrumb_renders_home_then_items_and_marks_the_last_as_current(): void
    {
        $this->blade('<x-breadcrumb :items="$items" />', [
            'items' => [
                ['label' => 'Dokter', 'url' => '/dokter'],
                ['label' => 'dr. Andi'],
            ],
        ])
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('<ol class="breadcrumb">', false)
            ->assertSeeInOrder(['Beranda', 'Dokter', 'dr. Andi'])
            ->assertSee('<li class="breadcrumb-item"><a href="'.url('/').'">Beranda</a></li>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="/dokter">Dokter</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">dr. Andi</li>', false);
    }

    public function test_breadcrumb_for_admin_starts_with_dasbor(): void
    {
        $this->blade('<x-breadcrumb home="admin.dashboard" :items="$items" />', [
            'items' => [['label' => 'Dokter']],
        ])
            ->assertSee('<li class="breadcrumb-item"><a href="'.url('/admin').'">Dasbor</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Dokter</li>', false)
            ->assertDontSee('Beranda');
    }

    public function test_breadcrumb_for_the_patient_portal_starts_with_portal_pasien(): void
    {
        $this->blade('<x-breadcrumb home="patient.dashboard" :items="$items" />', [
            'items' => [['label' => 'Profil dan Akun']],
        ])
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('patient.dashboard').'">Portal Pasien</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Profil dan Akun</li>', false)
            ->assertDontSee('Beranda')
            ->assertDontSee('Dasbor');
    }

    public function test_breadcrumb_without_items_shows_home_as_the_current_page(): void
    {
        $this->blade('<x-breadcrumb />')
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Beranda</li>', false)
            ->assertDontSee('<a href', false);
    }

    public function test_breadcrumb_escapes_labels_and_urls(): void
    {
        $this->blade('<x-breadcrumb :items="$items" />', [
            'items' => [
                ['label' => '<script>alert(1)</script>', 'url' => '/x?a=1&b="2"'],
                ['label' => 'Akhir'],
            ],
        ])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('href="/x?a=1&amp;b=&quot;2&quot;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_page_header_renders_single_h1_with_subtitle_icon_and_actions(): void
    {
        $view = $this->blade(<<<'BLADE'
            <x-page-header title="Daftar Dokter" subtitle="Kelola data dokter." icon="bi-person-badge">
                <x-slot:actions>
                    <a href="/admin/dokter/baru" class="btn btn-primary">Tambah Dokter</a>
                </x-slot:actions>
                <span class="badge text-bg-secondary">Info tambahan</span>
            </x-page-header>
            BLADE);

        $view->assertSee('Daftar Dokter')
            ->assertSee('Kelola data dokter.')
            ->assertSee('<i class="bi bi-person-badge me-2" aria-hidden="true"></i>', false)
            ->assertSee('page-header-actions', false)
            ->assertSee('Tambah Dokter')
            ->assertSee('Info tambahan');

        $this->assertSame(1, substr_count((string) $view, '<h1'));
    }

    public function test_page_header_without_optional_parts_renders_only_the_title(): void
    {
        $this->blade('<x-page-header title="Dasbor" />')
            ->assertSee('Dasbor')
            ->assertDontSee('page-header-actions', false)
            ->assertDontSee('text-secondary', false)
            ->assertDontSee('<i class="bi', false);
    }

    public function test_page_header_escapes_title_and_subtitle(): void
    {
        $this->blade('<x-page-header :title="$title" :subtitle="$title" />', ['title' => '<script>alert(1)</script>'])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_empty_state_renders_status_role_title_message_icon_and_action(): void
    {
        $this->blade(<<<'BLADE'
            <x-empty-state title="Belum ada dokter" message="Tambahkan dokter pertama Anda." icon="bi-person">
                <a href="/admin/dokter/baru" class="btn btn-primary">Tambah Dokter</a>
            </x-empty-state>
            BLADE)
            ->assertSee('role="status"', false)
            ->assertSee('<i class="bi bi-person empty-state-icon d-block mb-2" aria-hidden="true"></i>', false)
            ->assertSeeInOrder(['Belum ada dokter', 'Tambahkan dokter pertama Anda.', 'Tambah Dokter']);
    }

    public function test_empty_state_defaults_to_inbox_icon_and_hides_missing_parts(): void
    {
        $this->blade('<x-empty-state title="Kosong" />')
            ->assertSee('bi bi-inbox empty-state-icon', false)
            ->assertSee('Kosong')
            ->assertDontSee('<div class="d-flex flex-wrap justify-content-center gap-2">', false)
            ->assertDontSee('mb-3', false);
    }

    public function test_empty_state_escapes_title_and_message(): void
    {
        $this->blade('<x-empty-state :title="$payload" :message="$payload" />', ['payload' => '<script>alert(1)</script>'])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
