<?php

namespace Tests\Feature\Audit;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AuditObserverTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    private function log(string $action): AuditLog
    {
        return AuditLog::query()->where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_creating_a_specialty_writes_specialty_created_with_an_indonesian_description(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin);

        $specialty = $this->createSpecialty(['name' => 'Anak', 'description' => 'Layanan anak.']);

        $log = $this->log('specialty.created');

        $this->assertSame('Spesialisasi "Anak" ditambahkan.', $log->description);
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame(Specialty::class, $log->auditable_type);
        $this->assertSame($specialty->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertSame('Anak', $log->new_values['name']);
        $this->assertSame('anak', $log->new_values['slug']);
        $this->assertSame('[konten diubah]', $log->new_values['description'], 'Teks panjang hanya dicatat berubah.');
        $this->assertArrayNotHasKey('created_at', $log->new_values);
        $this->assertArrayNotHasKey('updated_at', $log->new_values);
    }

    public function test_updating_records_only_changed_columns_with_old_and_new_values(): void
    {
        $admin = $this->createStaff('content-admin');
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak', 'location' => 'Gedung A Lt. 1', 'is_active' => true]);
        AuditLog::query()->delete();

        $this->actingAs($admin);
        $clinic->update(['location' => 'Gedung B Lt. 2', 'is_active' => false]);

        $log = $this->log('clinic.updated');

        $this->assertSame('Poliklinik "Poliklinik Anak" diperbarui.', $log->description);
        $this->assertEquals(['location' => 'Gedung A Lt. 1', 'is_active' => 1], $log->old_values);
        $this->assertEquals(['location' => 'Gedung B Lt. 2', 'is_active' => 0], $log->new_values);
        $this->assertSame(1, AuditLog::query()->where('action', 'clinic.updated')->count());
    }

    public function test_rich_text_changes_are_logged_as_content_changed_only(): void
    {
        $clinic = $this->createClinic(['description' => '<p>Isi lama</p>']);
        AuditLog::query()->delete();

        $clinic->update(['description' => '<p>Isi baru</p>']);

        $log = $this->log('clinic.updated');

        $this->assertSame(['description' => '[konten diubah]'], $log->new_values);
        $this->assertSame(['description' => '[konten diubah]'], $log->old_values);
        $this->assertStringNotContainsString('Isi baru', (string) json_encode($log->new_values));
    }

    public function test_saves_without_real_changes_write_nothing(): void
    {
        $specialty = $this->createSpecialty();
        AuditLog::query()->delete();

        $specialty->save();
        $specialty->touch();
        $specialty->update(['name' => $specialty->name]);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_deleting_writes_the_old_values(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Mata']);
        AuditLog::query()->delete();

        $specialty->delete();

        $log = $this->log('specialty.deleted');

        $this->assertSame('Spesialisasi "Mata" dihapus.', $log->description);
        $this->assertSame('Mata', $log->old_values['name']);
        $this->assertNull($log->new_values);
        $this->assertSame($specialty->id, (int) $log->auditable_id);
    }

    public function test_clinic_events_use_the_clinic_prefix_and_entity_label(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Jantung']);
        $clinic->delete();

        $this->assertSame('Poliklinik "Poliklinik Jantung" ditambahkan.', $this->log('clinic.created')->description);
        $this->assertSame('Poliklinik "Poliklinik Jantung" dihapus.', $this->log('clinic.deleted')->description);
    }

    public function test_without_a_logged_in_user_the_actor_is_system(): void
    {
        $this->createSpecialty();

        $log = $this->log('specialty.created');

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
    }

    public function test_a_logged_in_patient_is_recorded_as_system_not_as_a_patient_actor(): void
    {
        $this->actingAs(User::factory()->create());

        $this->createSpecialty();

        $log = $this->log('specialty.created');

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
    }

    public function test_doctor_is_not_observed(): void
    {
        $doctor = $this->createDoctor();
        AuditLog::query()->delete();

        $doctor->update(['name' => 'Nama Baru']);
        $doctor->specialties()->detach();

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_the_observer_is_registered_for_exactly_specialty_and_clinic(): void
    {
        $this->createSpecialty();
        $this->createClinic();

        $this->assertSame(
            ['clinic.created', 'specialty.created'],
            AuditLog::query()->orderBy('action')->pluck('action')->all(),
        );
        $this->assertSame(1, Clinic::query()->count());
    }
}
