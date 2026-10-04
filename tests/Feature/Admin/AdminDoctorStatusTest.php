<?php

namespace Tests\Feature\Admin;

use App\Enums\DoctorStatus;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorStatusTest extends TestCase
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
    public function test_deactivating_and_reactivating_a_doctor_crd03(string $role): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);
        $slug = $doctor->slug;
        $this->actingAs($this->createStaff($role));

        $this->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.status', $doctor), ['status' => 'inactive', 'reason' => 'Cuti panjang'])
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHas('success', 'Dokter dinonaktifkan.');

        $doctor->refresh();
        $this->assertSame(DoctorStatus::Inactive, $doctor->status);
        $this->assertNull($doctor->archived_at);
        $this->assertSame($slug, $doctor->slug);
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.status_changed']);
        $this->assertNotContains('Laras Wulandari', Doctor::query()->publiclyVisible()->pluck('name')->all());

        $this->from(route('admin.doctors.index'))
            ->patch(route('admin.doctors.status', $doctor), ['status' => 'active'])
            ->assertRedirect(route('admin.doctors.index'))
            ->assertSessionHas('success', 'Dokter diaktifkan.');

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_an_archived_doctor_is_restored_as_inactive_and_archived_at_is_cleared(): void
    {
        $doctor = Doctor::factory()->archived()->withSpecialties()->create(['name' => 'Lilis Marlina']);
        $this->assertNotNull($doctor->archived_at);

        $this->actingAs($this->createStaff('content-admin'))
            ->patch(route('admin.doctors.status', $doctor), ['status' => 'inactive'])
            ->assertSessionHas('success', 'Dokter dipulihkan sebagai nonaktif.');

        $doctor->refresh();
        $this->assertSame(DoctorStatus::Inactive, $doctor->status);
        $this->assertNull($doctor->archived_at);
        $this->assertNotContains('Lilis Marlina', Doctor::query()->publiclyVisible()->pluck('name')->all());
    }

    public function test_an_archived_doctor_cannot_jump_straight_to_active(): void
    {
        $doctor = Doctor::factory()->archived()->withSpecialties()->create();

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.status', $doctor), ['status' => 'active'])
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHasErrors(['status' => 'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.'])
            ->assertSessionHas('error', 'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.');

        $fresh = $doctor->fresh();
        $this->assertSame(DoctorStatus::Archived, $fresh->status);
        $this->assertNotNull($fresh->archived_at);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'status kosong' => [['status' => ''], 'status', 'Status dokter wajib dipilih.'],
            'status arsip tidak boleh lewat form status' => [['status' => 'archived'], 'status', 'Status dokter tidak valid.'],
            'status asing' => [['status' => 'cuti'], 'status', 'Status dokter tidak valid.'],
            'alasan bukan teks' => [['status' => 'inactive', 'reason' => ['a']], 'reason', 'Alasan harus berupa teks.'],
            'alasan terlalu panjang' => [['status' => 'inactive', 'reason' => 'x256'], 'reason', 'Alasan maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_status_input_is_rejected(array $input, string $field, string $message): void
    {
        $doctor = $this->createDoctor();
        $input = array_map(fn ($value) => $value === 'x256' ? str_repeat('x', 256) : $value, $input);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.status', $doctor), $input)
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_registration_officer_patient_and_guest_cannot_change_status(): void
    {
        $doctor = $this->createDoctor();
        $url = route('admin.doctors.status', $doctor);

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user)->patch($url, ['status' => 'inactive'])->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->patch($url, ['status' => 'inactive'])->assertRedirect(route('admin.login'));

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_status_routes_are_guarded_by_the_manage_permission(): void
    {
        $routes = Route::getRoutes();

        foreach (['admin.doctors.status', 'admin.doctors.archive', 'admin.doctors.photo'] as $name) {
            $middleware = $routes->getByName($name)->gatherMiddleware();

            $this->assertContains('permission:doctors.manage', $middleware, $name);
            $this->assertContains('account:staff', $middleware, $name);
        }

        $this->assertSame(url('/admin/dokter/5/status'), route('admin.doctors.status', 5));
        $this->assertSame(url('/admin/dokter/5/arsip'), route('admin.doctors.archive', 5));
        $this->assertSame(url('/admin/dokter/5/foto'), route('admin.doctors.photo', 5));
    }

    public function test_list_shows_status_buttons_per_status_to_managers_only(): void
    {
        $active = $this->createDoctor(['name' => 'Dokter Aktif Satu']);
        $inactive = Doctor::factory()->inactive()->withSpecialties()->create(['name' => 'Dokter Nonaktif Satu']);
        $archived = Doctor::factory()->archived()->withSpecialties()->create(['name' => 'Dokter Arsip Satu']);

        $page = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index', ['status' => 'semua']))
            ->assertOk();

        $page->assertSee('data-confirm-action="'.route('admin.doctors.status', $active).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.archive', $active).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.status', $inactive).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.archive', $inactive).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.status', $archived).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.doctors.archive', $archived).'"', false)
            ->assertSee('Nonaktifkan')
            ->assertSee('Aktifkan')
            ->assertSee('Pulihkan')
            ->assertSee('Arsipkan')
            ->assertSee('id="modal-doctor-deactivate"', false)
            ->assertSee('id="modal-doctor-activate"', false)
            ->assertSee('id="modal-doctor-restore"', false)
            ->assertSee('id="modal-doctor-archive"', false)
            ->assertSee('name="status" value="inactive"', false)
            ->assertSee('name="reason"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.index', ['status' => 'semua']))
            ->assertOk()
            ->assertDontSee('data-confirm-action', false)
            ->assertDontSee('modal-doctor-deactivate', false)
            ->assertDontSee('Arsipkan');
    }

    public function test_detail_page_has_the_same_status_actions_for_managers(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertSee('data-confirm-action="'.route('admin.doctors.status', $doctor).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.archive', $doctor).'"', false)
            ->assertSee('id="modal-doctor-archive"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertDontSee('data-confirm-action', false);
    }
}
