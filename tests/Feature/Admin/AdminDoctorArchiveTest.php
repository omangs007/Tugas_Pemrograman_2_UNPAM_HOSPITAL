<?php

namespace Tests\Feature\Admin;

use App\Enums\DoctorStatus;
use App\Http\Requests\Admin\ArchiveDoctorRequest;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Doctors\DoctorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorArchiveTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function managers(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('managers')]
    public function test_archiving_sets_status_and_archived_at_and_hides_the_doctor_crd03(string $role): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);
        $slug = $doctor->slug;

        $this->actingAs($this->createStaff($role))
            ->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.archive', $doctor), ['reason' => 'Pindah tugas'])
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHas('success', 'Dokter diarsipkan.');

        $doctor->refresh();
        $this->assertSame(DoctorStatus::Archived, $doctor->status);
        $this->assertNotNull($doctor->archived_at);
        $this->assertTrue($doctor->archived_at->diffInMinutes(now(), true) < 2);
        $this->assertSame($slug, $doctor->slug);
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.archived']);
        $this->assertNotContains('Laras Wulandari', Doctor::query()->publiclyVisible()->pluck('name')->all());
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }

    public function test_an_inactive_doctor_can_be_archived_and_then_restored_and_archived_again(): void
    {
        $doctor = Doctor::factory()->inactive()->withSpecialties()->create();
        $this->actingAs($this->createStaff('content-admin'));

        $this->patch(route('admin.doctors.archive', $doctor))->assertSessionHas('success', 'Dokter diarsipkan.');
        $this->assertSame(DoctorStatus::Archived, $doctor->fresh()->status);

        $this->patch(route('admin.doctors.status', $doctor), ['status' => 'inactive'])->assertSessionHas('success');
        $restored = $doctor->fresh();
        $this->assertSame(DoctorStatus::Inactive, $restored->status);
        $this->assertNull($restored->archived_at);

        $this->patch(route('admin.doctors.archive', $doctor))->assertSessionHas('success', 'Dokter diarsipkan.');
        $this->assertNotNull($doctor->fresh()->archived_at);
    }

    public function test_archiving_an_already_archived_doctor_is_refused_without_touching_the_date(): void
    {
        $doctor = Doctor::factory()->archived()->withSpecialties()->create();
        $archivedAt = $doctor->archived_at->toDateTimeString();

        $this->actingAs($this->createStaff('content-admin'))
            ->patch(route('admin.doctors.archive', $doctor))
            ->assertSessionHas('error', 'Dokter sudah diarsipkan.');

        $this->assertSame($archivedAt, $doctor->fresh()->archived_at->toDateTimeString());
    }

    public function test_the_phase_two_service_reports_no_blocking_reason(): void
    {
        $doctor = $this->createDoctor();

        $this->assertNull(app(DoctorService::class)->archiveBlockedReason($doctor));
    }

    #[DataProvider('managers')]
    public function test_archive_is_refused_when_future_active_appointments_exist_even_for_super_admin(string $role): void
    {
        $this->app->bind(ArchiveDoctorRequest::class, fn () => new class extends ArchiveDoctorRequest
        {
            protected function blockedReason(Doctor $doctor): ?string
            {
                return 'Dokter masih memiliki 3 pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.';
            }
        });
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);
        $message = 'Dokter masih memiliki 3 pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.';

        $this->actingAs($this->createStaff($role))
            ->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.archive', $doctor), ['reason' => 'Coba arsip'])
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHasErrors(['status' => $message])
            ->assertSessionHas('error', $message);

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
        $this->assertNull($doctor->fresh()->archived_at);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'doctor.archived']);
    }

    public function test_reason_must_be_text_of_at_most_255_characters(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('content-admin'))->from(route('admin.doctors.show', $doctor));

        $this->patch(route('admin.doctors.archive', $doctor), ['reason' => str_repeat('x', 256)])
            ->assertSessionHasErrors(['reason' => 'Alasan maksimal 255 karakter.']);

        $this->patch(route('admin.doctors.archive', $doctor), ['reason' => ['a']])
            ->assertSessionHasErrors(['reason' => 'Alasan harus berupa teks.']);

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_registration_officer_patient_and_guest_cannot_archive(): void
    {
        $doctor = $this->createDoctor();
        $url = route('admin.doctors.archive', $doctor);

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user)->patch($url)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->patch($url)->assertRedirect(route('admin.login'));

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_there_is_no_way_to_delete_a_doctor(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('super-admin'));

        $this->delete(route('admin.doctors.show', $doctor))->assertStatus(405);
        $this->delete(route('admin.doctors.archive', $doctor))->assertStatus(405);

        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }
}
