<?php

namespace Tests\Feature\Facilities;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithFacilities;
use Tests\TestCase;

/**
 * Model ContactMessage (09 §6.8, F4B-R24, F4B-R35): cast status, `ip_hash` tersembunyi, `handled_*` tidak fillable.
 */
class ContactMessageModelTest extends TestCase
{
    use InteractsWithFacilities;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    public function test_fillable_hidden_and_casts_follow_the_blueprint(): void
    {
        $message = new ContactMessage;

        $this->assertSame(['name', 'email', 'phone', 'subject', 'message', 'status', 'consent_at', 'ip_hash'], $message->getFillable());
        $this->assertSame(['ip_hash'], $message->getHidden());
        $this->assertSame(ContactMessageStatus::class, $message->getCasts()['status']);
        $this->assertSame('datetime', $message->getCasts()['consent_at']);
        $this->assertSame('datetime', $message->getCasts()['handled_at']);
        $this->assertNotContains('handled_by', $message->getFillable());
        $this->assertNotContains('handled_at', $message->getFillable());
    }

    public function test_handled_columns_cannot_be_mass_assigned_but_can_be_force_filled(): void
    {
        $staff = User::factory()->staff()->create();
        $message = $this->createContactMessage();

        try {
            $message->fill(['handled_by' => $staff->id]);
            $this->fail('handled_by tidak boleh diisi lewat fill().');
        } catch (MassAssignmentException) {
            $this->assertNull($message->fresh()->handled_by);
        }

        $message->forceFill(['handled_by' => $staff->id, 'handled_at' => '2026-10-05 11:00:00'])->save();

        $fresh = $message->fresh();

        $this->assertSame($staff->id, $fresh->handled_by);
        $this->assertSame('2026-10-05 11:00:00', $fresh->handled_at->toDateTimeString());
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        ContactMessage::factory()->make()->fill(['bukan_kolom' => 'x']);
    }

    public function test_status_defaults_to_new_and_is_cast_to_the_enum(): void
    {
        $created = ContactMessage::query()->create([
            'name' => 'Rudi Hartono', 'email' => 'rudi@contoh.test', 'subject' => 'Pertanyaan jam operasional',
            'message' => 'Jam operasional poliklinik hari Sabtu?', 'consent_at' => now(), 'ip_hash' => str_repeat('a', 64),
        ]);
        $message = ContactMessage::query()->findOrFail($created->id);

        $this->assertSame(ContactMessageStatus::New, $message->status);
        $this->assertNull($message->phone);
        $this->assertNull($message->handled_by);
        $this->assertNull($message->handled_at);
        $this->assertSame('2026-10-05 10:00:00', $message->consent_at->toDateTimeString());
    }

    public function test_factory_defaults_and_states(): void
    {
        $new = $this->createContactMessage();
        $read = ContactMessage::factory()->read()->create();
        $replied = ContactMessage::factory()->replied()->create();
        $archived = ContactMessage::factory()->archived()->create();

        $this->assertSame(ContactMessageStatus::New, $new->fresh()->status);
        $this->assertNull($new->fresh()->handled_by);
        $this->assertSame(ContactMessageStatus::Read, $read->fresh()->status);
        $this->assertNull($read->fresh()->handled_by);
        $this->assertSame(ContactMessageStatus::Replied, $replied->fresh()->status);
        $this->assertNotNull($replied->fresh()->handled_by);
        $this->assertNotNull($replied->fresh()->handled_at);
        $this->assertSame(ContactMessageStatus::Archived, $archived->fresh()->status);
        $this->assertNotNull($archived->fresh()->handled_by);
        $this->assertGreaterThanOrEqual(10, mb_strlen($new->message));
        $this->assertNotNull($new->consent_at);
    }

    public function test_ip_hash_is_a_sha256_digest_and_is_hidden_from_serialization(): void
    {
        $message = $this->createContactMessage();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $message->ip_hash);
        $this->assertNotSame('127.0.0.1', $message->ip_hash);
        $this->assertArrayNotHasKey('ip_hash', $message->toArray());
        $this->assertArrayNotHasKey('ip_hash', json_decode($message->toJson(), true));
        $this->assertArrayHasKey('ip_hash', $message->makeVisible('ip_hash')->toArray());
    }

    public function test_unread_scope_returns_only_new_messages(): void
    {
        $new1 = $this->createContactMessage();
        ContactMessage::factory()->read()->create();
        ContactMessage::factory()->replied()->create();
        ContactMessage::factory()->archived()->create();
        $new2 = $this->createContactMessage();

        $this->assertSame([$new1->id, $new2->id], ContactMessage::query()->unread()->orderBy('id')->pluck('id')->all());
        $this->assertSame(5, ContactMessage::query()->count());
    }

    public function test_handler_relation_needs_eager_loading_in_strict_mode(): void
    {
        $staff = User::factory()->staff()->create();
        $this->createContactMessage(['status' => ContactMessageStatus::Replied, 'handled_by' => $staff->id, 'handled_at' => '2026-10-05 11:00:00']);
        $this->createContactMessage();
        $this->createContactMessage();

        $loaded = ContactMessage::query()->with('handler')->orderBy('id')->get();

        $this->assertTrue($loaded[0]->handler->is($staff));
        $this->assertNull($loaded[1]->handler);

        $lazy = ContactMessage::query()->orderBy('id')->get();

        $this->expectException(LazyLoadingViolationException::class);

        $lazy->first()->handler;
    }

    public function test_text_is_stored_verbatim_without_sanitization(): void
    {
        $message = $this->createContactMessage([
            'name' => '<script>alert(1)</script>',
            'subject' => '<b>Subjek</b> & "kutip"',
            'message' => "Baris satu.\n<img src=x onerror=alert(1)>\nBaris tiga.",
        ]);

        $fresh = $message->fresh();

        $this->assertSame('<script>alert(1)</script>', $fresh->name);
        $this->assertSame('<b>Subjek</b> & "kutip"', $fresh->subject);
        $this->assertSame("Baris satu.\n<img src=x onerror=alert(1)>\nBaris tiga.", $fresh->message);
    }
}
