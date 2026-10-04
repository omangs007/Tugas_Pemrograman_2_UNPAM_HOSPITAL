<?php

namespace Tests\Feature\View;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class ModuleTabsComponentTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/uji/tab-satu', fn () => Blade::render('<x-module-tabs group="uji" />'))->name('uji.tab-satu');
        Route::get('/uji/tab-dua', fn () => Blade::render('<x-module-tabs group="uji" />'))->name('uji.tab-dua');
        Route::get('/uji/kosong', fn () => Blade::render('<x-module-tabs group="tidak-ada" />'))->name('uji.kosong');
        Route::getRoutes()->refreshNameLookups();

        config(['navigation.modules.uji' => [
            ['label' => 'Satu', 'route' => 'uji.tab-satu', 'permission' => 'clinics.manage', 'active' => ['uji.tab-satu']],
            ['label' => 'Dua', 'route' => 'uji.tab-dua', 'active' => ['uji.tab-dua']],
            ['label' => 'Hantu', 'route' => 'uji.tidak-pernah-ada', 'active' => ['uji.tidak-pernah-ada']],
        ]]);
    }

    public function test_renders_tabs_and_marks_only_the_current_one(): void
    {
        $response = $this->actingAs($this->createStaff('content-admin'))->get('/uji/tab-satu');

        $response->assertOk()
            ->assertSee('<nav aria-label="Tab modul">', false)
            ->assertSee('<ul class="nav nav-tabs mb-3">', false)
            ->assertSee('<a class="nav-link active" href="'.route('uji.tab-satu').'" aria-current="page">Satu</a>', false)
            ->assertSee('<a class="nav-link" href="'.route('uji.tab-dua').'">Dua</a>', false);

        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
    }

    public function test_second_tab_becomes_current_on_its_own_page(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/uji/tab-dua')
            ->assertSee('<a class="nav-link active" href="'.route('uji.tab-dua').'" aria-current="page">Dua</a>', false)
            ->assertSee('<a class="nav-link" href="'.route('uji.tab-satu').'">Satu</a>', false);
    }

    public function test_tabs_to_missing_routes_are_dropped(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/uji/tab-satu')
            ->assertDontSee('Hantu');
    }

    public function test_tabs_are_filtered_by_permission(): void
    {
        // Petugas Pendaftaran tidak memegang clinics.manage: hanya tab tanpa syarat izin yang tampil.
        $this->actingAs($this->createStaff('registration-officer'))->get('/uji/tab-dua')
            ->assertSee('>Dua</a>', false)
            ->assertDontSee('>Satu</a>', false);
    }

    public function test_guest_sees_only_tabs_without_a_permission_requirement(): void
    {
        $this->get('/uji/tab-dua')
            ->assertOk()
            ->assertSee('>Dua</a>', false)
            ->assertDontSee('>Satu</a>', false);
    }

    public function test_renders_nothing_when_no_tab_survives_or_the_group_is_unknown(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/uji/kosong')
            ->assertOk()
            ->assertDontSee('<nav', false);

        config(['navigation.modules.uji' => [
            ['label' => 'Hantu', 'route' => 'uji.tidak-pernah-ada'],
        ]]);

        $this->actingAs($this->createStaff('content-admin'))->get('/uji/tab-satu')
            ->assertOk()
            ->assertDontSee('<nav', false);
    }

    public function test_clinics_group_in_the_navigation_config_has_the_documented_shape(): void
    {
        $this->assertSame([
            ['label' => 'Poliklinik', 'route' => 'admin.clinics.index', 'permission' => 'clinics.manage', 'active' => ['admin.clinics.*']],
            ['label' => 'Spesialisasi', 'route' => 'admin.specialties.index', 'permission' => 'clinics.manage', 'active' => ['admin.specialties.*']],
        ], config('navigation.modules.clinics'));
    }
}
