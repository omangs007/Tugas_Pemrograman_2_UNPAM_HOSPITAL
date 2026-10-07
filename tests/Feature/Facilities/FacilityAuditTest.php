<?php

namespace Tests\Feature\Facilities;

use App\Enums\ActorType;
use App\Enums\ContactMessageStatus;
use App\Enums\FacilityCategory;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F4B-R25 / 12 §8.3: Facility dan RoomCategory diobservasi AuditObserver dengan deskripsi berbahasa Indonesia;
 * ContactMessage tidak diobservasi agar nama pengirim tidak masuk deskripsi audit.
 */
class FacilityAuditTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createStaff('content-admin');
        $this->actingAs($this->admin);
    }

    private function log(string $action): AuditLog
    {
        return AuditLog::query()->where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_entity_labels_exist_for_the_three_new_models_and_compose_with_the_flash_templates(): void
    {
        $this->assertSame('Fasilitas', __('messages.entities.facility'));
        $this->assertSame('Kategori kamar', __('messages.entities.room_category'));
        $this->assertSame('Pesan kontak', __('messages.entities.contact_message'));
        $this->assertSame('Fasilitas berhasil ditambahkan.', __('messages.created', ['entity' => __('messages.entities.facility')]));
        $this->assertSame('Kategori kamar berhasil diperbarui.', __('messages.updated', ['entity' => __('messages.entities.room_category')]));
        $this->assertSame('Fasilitas berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.facility')]));
        $this->assertSame('Kategori kamar berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.room_category')]));
    }

    public function test_creating_each_audited_model_writes_its_own_action_and_the_contact_message_writes_none(): void
    {
        $this->createFacility();
        $this->createRoomCategory();
        $this->createContactMessage();

        $actions = AuditLog::query()->pluck('action')->all();
        sort($actions);

        $this->assertSame(['facility.created', 'room_category.created'], $actions);
    }

    public function test_facility_lifecycle(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium', 'category' => FacilityCategory::Penunjang]);

        $created = $this->log('facility.created');
        $this->assertSame('Fasilitas "Laboratorium" ditambahkan.', $created->description);
        $this->assertSame(ActorType::Staff, $created->actor_type);
        $this->assertSame($this->admin->id, (int) $created->user_id);
        $this->assertSame(Facility::class, $created->auditable_type);
        $this->assertSame($facility->id, (int) $created->auditable_id);
        $this->assertNull($created->old_values);
        $this->assertSame('Laboratorium', $created->new_values['name']);
        $this->assertSame('penunjang', $created->new_values['category']);

        AuditLog::query()->delete();
        $facility->update(['is_active' => false]);

        $updated = $this->log('facility.updated');
        $this->assertSame('Fasilitas "Laboratorium" diperbarui.', $updated->description);
        $this->assertEquals(['is_active' => 1], $updated->old_values);
        $this->assertEquals(['is_active' => 0], $updated->new_values);

        AuditLog::query()->delete();
        $facility->delete();

        $deleted = $this->log('facility.deleted');
        $this->assertSame('Fasilitas "Laboratorium" dihapus.', $deleted->description);
        $this->assertSame('Laboratorium', $deleted->old_values['name']);
        $this->assertNull($deleted->new_values);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_changing_the_facility_category_records_the_raw_values(): void
    {
        $facility = $this->createFacility(['name' => 'Musala', 'category' => FacilityCategory::Umum]);
        AuditLog::query()->delete();

        $facility->update(['category' => FacilityCategory::Medis]);

        $log = $this->log('facility.updated');

        $this->assertSame(['category' => 'umum'], $log->old_values);
        $this->assertSame(['category' => 'medis'], $log->new_values);
    }

    public function test_the_description_is_logged_as_content_changed_only(): void
    {
        $facility = $this->createFacility(['description' => '<p>Isi lama yang cukup panjang.</p>']);
        AuditLog::query()->delete();

        $facility->update(['description' => '<p>Isi baru yang rahasia.</p>']);

        $log = $this->log('facility.updated');

        $this->assertSame(['description' => '[konten diubah]'], $log->new_values);
        $this->assertStringNotContainsString('rahasia', (string) json_encode($log->new_values));
    }

    public function test_room_category_lifecycle(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar VIP', 'bed_capacity' => 6, 'beds_available' => 2]);

        $created = $this->log('room_category.created');
        $this->assertSame('Kategori kamar "Kamar VIP" ditambahkan.', $created->description);
        $this->assertSame(ActorType::Staff, $created->actor_type);
        $this->assertSame(RoomCategory::class, $created->auditable_type);
        $this->assertSame($room->id, (int) $created->auditable_id);
        $this->assertSame('Kamar VIP', $created->new_values['name']);
        $this->assertEquals(6, $created->new_values['bed_capacity']);

        AuditLog::query()->delete();
        $room->update(['beds_available' => 1]);

        $updated = $this->log('room_category.updated');
        $this->assertSame('Kategori kamar "Kamar VIP" diperbarui.', $updated->description);
        $this->assertEquals(['beds_available' => 2], $updated->old_values);
        $this->assertEquals(['beds_available' => 1], $updated->new_values);

        AuditLog::query()->delete();
        $room->delete();

        $deleted = $this->log('room_category.deleted');
        $this->assertSame('Kategori kamar "Kamar VIP" dihapus.', $deleted->description);
        $this->assertSame('Kamar VIP', $deleted->old_values['name']);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_a_model_loaded_from_the_database_is_audited_without_a_missing_attribute_error(): void
    {
        $this->createFacility(['name' => 'Dimuat Ulang']);
        $this->createRoomCategory(['name' => 'Kamar Dimuat Ulang']);
        AuditLog::query()->delete();

        Facility::query()->firstOrFail()->update(['short_description' => 'Baru.']);
        RoomCategory::query()->firstOrFail()->update(['short_description' => 'Baru.']);

        $this->assertSame('Fasilitas "Dimuat Ulang" diperbarui.', $this->log('facility.updated')->description);
        $this->assertSame('Kategori kamar "Kamar Dimuat Ulang" diperbarui.', $this->log('room_category.updated')->description);
    }

    public function test_a_save_without_changes_writes_nothing(): void
    {
        $facility = $this->createFacility();
        $room = $this->createRoomCategory();
        AuditLog::query()->delete();

        $facility->save();
        $room->save();
        $room->update(['name' => $room->name]);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_without_a_logged_in_staff_the_actor_is_the_system(): void
    {
        auth()->forgetGuards();

        $this->createFacility(['name' => 'Tanpa Login']);

        $log = $this->log('facility.created');

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertSame('Fasilitas "Tanpa Login" ditambahkan.', $log->description);
    }

    public function test_contact_messages_are_never_audited_by_the_observer_even_when_changed_by_staff(): void
    {
        $officer = $this->createStaff('registration-officer');
        $this->actingAs($officer);
        AuditLog::query()->delete();

        $message = $this->createContactMessage(['name' => 'Rudi Hartono', 'subject' => 'Rahasia', 'email' => 'rudi@contoh.test']);
        $message->update(['status' => ContactMessageStatus::Read]);
        $message->forceFill(['status' => ContactMessageStatus::Replied, 'handled_by' => $officer->id, 'handled_at' => now()])->save();
        $message->delete();

        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'contact_message%')->count());
        $this->assertSame(0, AuditLog::query()->count());
    }
}
