<?php

namespace Tests\Feature\Facilities;

use App\Enums\ContactMessageStatus;
use App\Enums\FacilityCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithFacilities;
use Tests\TestCase;

/**
 * Menjaga pembantu tes fasilitas, kamar, dan pesan kontak, karena dipakai hampir semua tes fase 4b.
 */
class InteractsWithFacilitiesTest extends TestCase
{
    use InteractsWithFacilities;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    public function test_create_facility_builds_an_active_general_facility_and_applies_overrides(): void
    {
        $plain = $this->createFacility();
        $custom = $this->createFacility(['name' => 'Musala', 'category' => FacilityCategory::Umum, 'is_active' => false, 'sort_order' => 4]);

        $this->assertTrue($plain->is_active);
        $this->assertSame(FacilityCategory::Umum, $plain->category);
        $this->assertNotSame('', $plain->slug);
        $this->assertSame('Musala', $custom->name);
        $this->assertSame('musala', $custom->slug);
        $this->assertFalse($custom->is_active);
        $this->assertSame(4, $custom->sort_order);
    }

    public function test_create_room_category_builds_a_fresh_active_category_and_applies_overrides(): void
    {
        $plain = $this->createRoomCategory();
        $explicit = $this->createRoomCategory(['name' => 'Kamar VIP', 'slug' => 'vip', 'bed_capacity' => 6, 'beds_available' => 2, 'availability_updated_at' => null]);

        $this->assertTrue($plain->is_active);
        $this->assertSame(5, $plain->beds_available);
        $this->assertSame(12, $plain->bed_capacity);
        $this->assertFalse($plain->is_availability_stale);
        $this->assertSame('vip', $explicit->slug);
        $this->assertSame(2, $explicit->beds_available);
        $this->assertNull($explicit->availability_updated_at);
        $this->assertTrue($explicit->is_availability_stale);
    }

    public function test_create_contact_message_builds_a_new_message_with_consent_and_ip_hash(): void
    {
        $message = $this->createContactMessage(['subject' => 'Pertanyaan jam operasional']);

        $this->assertSame(ContactMessageStatus::New, $message->fresh()->status);
        $this->assertSame('Pertanyaan jam operasional', $message->subject);
        $this->assertNotNull($message->fresh()->consent_at);
        $this->assertSame(64, strlen($message->ip_hash));
        $this->assertNull($message->fresh()->handled_by);
    }

    public function test_create_contact_message_accepts_handled_columns_through_force_fill(): void
    {
        $staff = User::factory()->staff()->create();

        $message = $this->createContactMessage([
            'status' => ContactMessageStatus::Replied,
            'handled_by' => $staff->id,
            'handled_at' => '2026-10-05 09:30:00',
        ]);

        $fresh = $message->fresh();

        $this->assertSame(ContactMessageStatus::Replied, $fresh->status);
        $this->assertSame($staff->id, $fresh->handled_by);
        $this->assertSame('2026-10-05 09:30:00', $fresh->handled_at->toDateTimeString());
    }

    public function test_create_contact_message_leaves_handled_columns_empty_when_not_given(): void
    {
        $message = $this->createContactMessage(['status' => ContactMessageStatus::Read]);

        $this->assertSame(ContactMessageStatus::Read, $message->fresh()->status);
        $this->assertNull($message->fresh()->handled_by);
        $this->assertNull($message->fresh()->handled_at);
    }
}
