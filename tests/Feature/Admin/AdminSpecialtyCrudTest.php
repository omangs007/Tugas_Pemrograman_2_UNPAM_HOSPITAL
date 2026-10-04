<?php

namespace Tests\Feature\Admin;

use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminSpecialtyCrudTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Contoh Baru',
            'description' => 'Spesialisasi contoh untuk pengujian.',
            'is_active' => '1',
            'sort_order' => '14',
        ], $overrides);
    }

    public function test_create_form_renders_for_content_admin_without_a_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.create'))
            ->assertOk()
            ->assertSee('Tambah Spesialisasi')
            ->assertSee('name="name"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('name="sort_order"', false)
            ->assertDontSee('name="slug"', false)
            ->assertSee('action="'.route('admin.specialties.store').'"', false);
    }

    public function test_content_admin_adds_edits_and_deactivates_a_specialty_crd01(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.specialties.store'), $this->payload())
            ->assertRedirect(route('admin.specialties.index'))
            ->assertSessionHas('success', 'Spesialisasi berhasil ditambahkan.');

        $specialty = Specialty::query()->where('name', 'Contoh Baru')->sole();

        $this->assertSame('contoh-baru', $specialty->slug);
        $this->assertTrue($specialty->is_active);
        $this->assertSame(14, $specialty->sort_order);
        $this->assertSame('Spesialisasi contoh untuk pengujian.', $specialty->description);
        $this->assertContains('Contoh Baru', Specialty::query()->active()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'specialty.created']);

        $this->put(route('admin.specialties.update', $specialty), $this->payload(['name' => 'Contoh Diubah', 'sort_order' => '3']))
            ->assertRedirect(route('admin.specialties.index'))
            ->assertSessionHas('success', 'Spesialisasi berhasil diperbarui.');

        $specialty->refresh();
        $this->assertSame('Contoh Diubah', $specialty->name);
        $this->assertSame('contoh-baru', $specialty->slug, 'Slug tidak berubah saat nama diedit.');
        $this->assertSame(3, $specialty->sort_order);
        $this->assertDatabaseHas('audit_logs', ['action' => 'specialty.updated']);

        $this->put(route('admin.specialties.update', $specialty), $this->payload(['name' => 'Contoh Diubah', 'is_active' => '0']))
            ->assertRedirect(route('admin.specialties.index'));

        $this->assertFalse($specialty->fresh()->is_active);
        $this->assertNotContains('Contoh Diubah', Specialty::query()->active()->pluck('name')->all());
    }

    public function test_optional_fields_default_when_omitted(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.specialties.store'), ['name' => 'Tanpa Opsional', 'is_active' => '1'])
            ->assertRedirect(route('admin.specialties.index'));

        $specialty = Specialty::query()->where('name', 'Tanpa Opsional')->sole();

        $this->assertNull($specialty->description);
        $this->assertSame(0, $specialty->sort_order);
    }

    public function test_two_specialties_with_equal_slug_source_get_numbered_slugs(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->createSpecialty(['name' => 'Anak', 'slug' => 'anak']);

        $this->actingAs($admin)->post(route('admin.specialties.store'), $this->payload(['name' => 'ANAK!']))
            ->assertRedirect(route('admin.specialties.index'));

        $this->assertSame('anak-2', Specialty::query()->where('name', 'ANAK!')->sole()->slug);
    }

    public function test_slug_and_unknown_fields_are_not_mass_assignable_from_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.specialties.store'), $this->payload(['slug' => 'dipaksa', 'id' => 999]))
            ->assertRedirect(route('admin.specialties.index'));

        $specialty = Specialty::query()->where('name', 'Contoh Baru')->sole();

        $this->assertSame('contoh-baru', $specialty->slug);
        $this->assertNotSame(999, $specialty->id);
    }

    public function test_edit_form_shows_current_values(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Urologi', 'description' => 'Saluran kemih.', 'sort_order' => 7]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.specialties.edit', $specialty))
            ->assertOk()
            ->assertSee('Ubah Spesialisasi')
            ->assertSee('value="Urologi"', false)
            ->assertSee('Saluran kemih.')
            ->assertSee('value="7"', false)
            ->assertSee('action="'.route('admin.specialties.update', $specialty).'"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    public function test_update_may_keep_the_own_name_but_not_take_another_name(): void
    {
        $urologi = $this->createSpecialty(['name' => 'Urologi']);
        $this->createSpecialty(['name' => 'Anak']);
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)->put(route('admin.specialties.update', $urologi), $this->payload(['name' => 'Urologi']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.specialties.index'));

        $this->put(route('admin.specialties.update', $urologi), $this->payload(['name' => 'anak']))
            ->assertSessionHasErrors(['name' => 'Nama spesialisasi sudah digunakan.']);
    }

    public function test_non_numeric_id_is_a_404_not_a_bound_model(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/spesialisasi/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_mutating_routes(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Urologi']);

        $requests = [
            ['get', route('admin.specialties.create'), []],
            ['post', route('admin.specialties.store'), $this->payload(['name' => 'Tidak Boleh'])],
            ['get', route('admin.specialties.edit', $specialty), []],
            ['put', route('admin.specialties.update', $specialty), $this->payload(['name' => 'Diubah Paksa'])],
            ['delete', route('admin.specialties.destroy', $specialty), []],
        ];

        $officer = $this->createStaff('registration-officer');
        $patient = User::factory()->patient()->create();

        foreach ($requests as [$method, $url, $data]) {
            $this->actingAs($officer);
            $this->call(strtoupper($method), $url, $data)->assertForbidden();

            $this->actingAs($patient);
            $this->call(strtoupper($method), $url, $data)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();

        foreach ($requests as [$method, $url, $data]) {
            $this->call(strtoupper($method), $url, $data)->assertRedirect(route('admin.login'));
        }

        $this->assertSame('Urologi', $specialty->fresh()->name);
        $this->assertDatabaseMissing('specialties', ['name' => 'Tidak Boleh']);
        $this->assertDatabaseMissing('specialties', ['name' => 'Diubah Paksa']);
        $this->assertDatabaseHas('specialties', ['id' => $specialty->id]);
    }
}
