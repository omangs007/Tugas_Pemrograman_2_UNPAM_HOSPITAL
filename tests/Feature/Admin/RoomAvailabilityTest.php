<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Http\Requests\Admin\UpdateRoomAvailabilityRequest;
use App\Models\AuditLog;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Aksi ketersediaan kamar (T5, F4B-R17, 06 §13, V-29, Review Focus 1): batas 0..kapasitas, izin Petugas, penanda waktu
 * dan petugas, satu audit `room.availability_updated`, dan CHECK database yang tidak pernah memicu 500.
 */
class RoomAvailabilityTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const OLD_STAMP = '2026-10-05 08:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 6 Oktober 2026 pukul 10.30 WIB.
        $this->travelTo(Carbon::parse('2026-10-06 10:30:00'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function room(array $overrides = []): RoomCategory
    {
        return $this->createRoomCategory(array_merge([
            'name' => 'VIP Contoh',
            'bed_capacity' => 12,
            'beds_available' => 4,
            'availability_updated_at' => Carbon::parse(self::OLD_STAMP),
            'availability_updated_by' => null,
        ], $overrides));
    }

    private function indexUrl(): string
    {
        return route('admin.room-categories.index');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedRoles(): array
    {
        return [
            'petugas pendaftaran' => ['registration-officer'],
            'admin konten' => ['content-admin'],
            'super admin' => ['super-admin'],
        ];
    }

    #[DataProvider('allowedRoles')]
    public function test_staff_with_the_availability_permission_updates_the_count_and_the_metadata(string $role): void
    {
        $room = $this->room();
        $user = $this->createStaff($role);

        $this->actingAs($user)
            ->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '2', 'availability_for' => (string) $room->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->indexUrl())
            ->assertSessionHas('success', 'Ketersediaan VIP Contoh berhasil diperbarui.');

        $room->refresh();
        $this->assertSame(2, $room->beds_available);
        $this->assertSame(12, $room->bed_capacity);
        $this->assertTrue($room->availability_updated_at->equalTo(now()));
        $this->assertSame($user->id, $room->availability_updated_by);
    }

    public function test_exactly_one_availability_audit_is_written_with_old_and_new_values_and_no_generic_update_audit(): void
    {
        $room = $this->room();
        $user = $this->createStaff('registration-officer');

        $this->actingAs($user)
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '2'])
            ->assertSessionHasNoErrors();

        $log = AuditLog::query()->where('action', 'room.availability_updated')->sole();

        $this->assertSame(['beds_available' => 4], $log->old_values);
        $this->assertSame(['beds_available' => 2], $log->new_values);
        $this->assertSame('Ketersediaan VIP Contoh diperbarui dari 4 menjadi 2 tempat tidur.', $log->description);
        $this->assertSame(RoomCategory::class, $log->auditable_type);
        $this->assertSame($room->id, $log->auditable_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame(0, AuditLog::query()->where('action', 'room_category.updated')->count());
    }

    public function test_the_same_value_still_refreshes_the_timestamp_as_a_confirmation(): void
    {
        $room = $this->room();
        $user = $this->createStaff('registration-officer');
        $this->travelTo(Carbon::parse('2026-10-06 14:00:00'));

        $this->actingAs($user)
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '4'])
            ->assertSessionHasNoErrors();

        $room->refresh();
        $this->assertSame(4, $room->beds_available);
        $this->assertSame('2026-10-06 14:00:00', $room->availability_updated_at->toDateTimeString());
        $this->assertSame($user->id, $room->availability_updated_by);
    }

    public function test_an_inactive_room_can_be_updated(): void
    {
        $room = $this->room(['is_active' => false]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $room->fresh()->beds_available);
    }

    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function boundaries(): array
    {
        return [
            'nol (penuh)' => ['0', null],
            'sama dengan kapasitas' => ['12', null],
            'kapasitas ditambah satu' => ['13', 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas (12 tempat tidur).'],
            'sangat besar' => ['99999', 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas (12 tempat tidur).'],
            'negatif' => ['-1', 'Jumlah tempat tidur tersedia tidak boleh kurang dari 0.'],
            'huruf' => ['abc', 'Jumlah tempat tidur tersedia harus berupa angka bulat.'],
            'desimal' => ['5.5', 'Jumlah tempat tidur tersedia harus berupa angka bulat.'],
            'kosong' => ['', 'Jumlah tempat tidur tersedia wajib diisi.'],
            'larik' => [['3'], 'Jumlah tempat tidur tersedia harus berupa angka bulat.'],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_the_value_must_be_between_zero_and_the_capacity(mixed $value, ?string $error): void
    {
        $room = $this->room();
        $this->actingAs($this->createStaff('registration-officer'));

        $response = $this->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => $value, 'availability_for' => (string) $room->id]);

        $room->refresh();

        if ($error === null) {
            $response->assertSessionHasNoErrors();
            $this->assertSame((int) $value, $room->beds_available);
            $this->assertSame(1, AuditLog::query()->where('action', 'room.availability_updated')->count());

            return;
        }

        $response->assertRedirect($this->indexUrl())->assertSessionHasErrors(['beds_available' => $error]);
        $this->assertSame(4, $room->beds_available);
        $this->assertSame(self::OLD_STAMP, $room->availability_updated_at->toDateTimeString());
        $this->assertNull($room->availability_updated_by);
        $this->assertSame(0, AuditLog::query()->where('action', 'room.availability_updated')->count());
    }

    public function test_the_validation_error_appears_only_in_the_submitted_row_with_the_typed_value(): void
    {
        $a = $this->room(['name' => 'BPJS Kelas 1 Contoh', 'sort_order' => 1, 'bed_capacity' => 12, 'beds_available' => 4]);
        $b = $this->room(['name' => 'BPJS Kelas 2 Contoh', 'sort_order' => 2, 'bed_capacity' => 16, 'beds_available' => 7]);
        $message = 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas (12 tempat tidur).';
        $this->actingAs($this->createStaff('registration-officer'));

        $this->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $a), ['beds_available' => '13', 'availability_for' => (string) $a->id])
            ->assertRedirect($this->indexUrl());

        $html = $this->get($this->indexUrl())->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, $message));
        $this->assertStringContainsString('name="beds_available" value="13" min="0" max="12"', $html);
        $this->assertStringContainsString('name="beds_available" value="7" min="0" max="16"', $html);
        $this->assertTrue(strpos($html, $message) < strpos($html, 'BPJS Kelas 2 Contoh'));
        $this->assertTrue(strpos($html, 'BPJS Kelas 1 Contoh') < strpos($html, $message));
        $this->assertSame(4, $a->fresh()->beds_available);
        $this->assertSame(7, $b->fresh()->beds_available);
    }

    public function test_array_input_does_not_break_the_next_index_render(): void
    {
        $room = $this->room();
        $this->actingAs($this->createStaff('registration-officer'));

        $this->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => ['3'], 'availability_for' => [(string) $room->id]])
            ->assertRedirect($this->indexUrl())
            ->assertSessionHasErrors(['beds_available', 'availability_for']);

        $this->get($this->indexUrl())->assertOk()->assertSee('VIP Contoh');
    }

    public function test_the_controller_rechecks_the_capacity_under_lock_even_if_validation_was_bypassed(): void
    {
        $room = $this->room();
        $this->app->bind(UpdateRoomAvailabilityRequest::class, fn () => new class extends UpdateRoomAvailabilityRequest
        {
            public function rules(): array
            {
                return ['beds_available' => ['required', 'integer', 'min:0'], 'availability_for' => ['nullable', 'integer']];
            }
        });
        $this->actingAs($this->createStaff('registration-officer'));

        $this->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '99', 'availability_for' => (string) $room->id])
            ->assertRedirect($this->indexUrl())
            ->assertSessionHasErrors(['beds_available' => 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas (12 tempat tidur).']);

        $room->refresh();
        $this->assertSame(4, $room->beds_available);
        $this->assertSame(self::OLD_STAMP, $room->availability_updated_at->toDateTimeString());
        $this->assertSame(0, AuditLog::query()->where('action', 'room.availability_updated')->count());
    }

    public function test_patient_and_guest_cannot_update_availability_and_unknown_ids_are_404(): void
    {
        $room = $this->room();
        $url = route('admin.room-categories.availability', $room);

        $this->actingAs(User::factory()->patient()->create())
            ->patch($url, ['beds_available' => '1'])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->patch($url, ['beds_available' => '1'])->assertRedirect(route('admin.login'));

        $this->actingAs($this->createStaff('registration-officer'));
        $this->patch('/admin/kamar/abc/ketersediaan', ['beds_available' => '1'])->assertNotFound();
        $this->patch('/admin/kamar/999999/ketersediaan', ['beds_available' => '1'])->assertNotFound();

        $this->assertSame(4, $room->fresh()->beds_available);
    }

    public function test_a_role_without_the_availability_permission_is_rejected_by_the_route_middleware(): void
    {
        $room = $this->room();
        $user = $this->createStaff('content-admin');
        $user->syncRoles([]);
        $user->givePermissionTo('facilities.manage');

        $this->actingAs($user)
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '1'])
            ->assertForbidden();

        $this->assertSame(4, $room->fresh()->beds_available);
    }
}
