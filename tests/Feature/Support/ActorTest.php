<?php

namespace Tests\Feature\Support;

use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Models\User;
use App\Support\Actor;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

class ActorTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_actor_has_no_user(): void
    {
        $actor = Actor::system();

        $this->assertSame(ActorType::System, $actor->type);
        $this->assertNull($actor->userId);
        $this->assertTrue($actor->isSystem());
        $this->assertFalse($actor->isStaff());
        $this->assertFalse($actor->isPatient());
        $this->assertNull($actor->user());
        $this->assertSame(BookingChannel::Online, $actor->channel());
    }

    public function test_staff_actor_wraps_the_staff_account(): void
    {
        $staff = User::factory()->staff()->create();

        $actor = Actor::staff($staff);

        $this->assertSame(ActorType::Staff, $actor->type);
        $this->assertSame($staff->id, $actor->userId);
        $this->assertTrue($actor->isStaff());
        $this->assertFalse($actor->isPatient());
        $this->assertFalse($actor->isSystem());
        $this->assertTrue($actor->user()->is($staff));
        $this->assertSame(BookingChannel::Loket, $actor->channel());
    }

    public function test_patient_actor_wraps_the_patient_account(): void
    {
        $patient = User::factory()->create();

        $actor = Actor::patient($patient);

        $this->assertSame(ActorType::Patient, $actor->type);
        $this->assertSame($patient->id, $actor->userId);
        $this->assertTrue($actor->isPatient());
        $this->assertFalse($actor->isStaff());
        $this->assertFalse($actor->isSystem());
        $this->assertTrue($actor->user()->is($patient));
        $this->assertSame(BookingChannel::Online, $actor->channel());
    }

    public function test_user_is_null_when_the_account_row_no_longer_exists(): void
    {
        $staff = User::factory()->staff()->create();
        $actor = Actor::staff($staff);

        DB::table('users')->where('id', $staff->id)->delete();

        $this->assertNull($actor->user());
    }

    public function test_human_actors_require_a_persisted_user(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Actor::staff(new User);
    }

    public function test_class_is_final_readonly_with_a_private_constructor(): void
    {
        $reflection = new ReflectionClass(Actor::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->getConstructor()->isPrivate());
    }

    public function test_actor_cannot_be_modified_after_creation(): void
    {
        $actor = Actor::system();

        $this->expectException(Error::class);

        $actor->userId = 5;
    }
}
