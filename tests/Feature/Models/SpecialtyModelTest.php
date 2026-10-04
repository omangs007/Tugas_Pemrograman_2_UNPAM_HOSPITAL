<?php

namespace Tests\Feature\Models;

use App\Models\Specialty;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_follows_the_blueprint(): void
    {
        $this->assertSame(
            ['name', 'slug', 'description', 'is_active', 'sort_order'],
            (new Specialty)->getFillable(),
        );
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        Specialty::create(['name' => 'Anak', 'slug' => 'anak', 'bukan_kolom' => 'x']);
    }

    public function test_casts_and_defaults(): void
    {
        $created = Specialty::create(['name' => 'Anak', 'slug' => 'anak']);
        $specialty = Specialty::query()->findOrFail($created->id);

        $this->assertTrue($specialty->is_active);
        $this->assertSame(0, $specialty->sort_order);
        $this->assertNull($specialty->description);
    }

    public function test_factory_creates_a_valid_active_specialty_and_inactive_state(): void
    {
        $active = Specialty::factory()->create();
        $inactive = Specialty::factory()->inactive()->create();

        $this->assertTrue($active->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);
        $this->assertNotSame('', $active->slug);
        $this->assertNotSame($active->slug, $inactive->slug);
    }

    public function test_active_scope_only_returns_active_rows(): void
    {
        $active = Specialty::factory()->create();
        Specialty::factory()->inactive()->count(2)->create();

        $this->assertSame([$active->id], Specialty::query()->active()->pluck('id')->all());
    }

    public function test_ordered_scope_sorts_by_sort_order_then_name(): void
    {
        $c = Specialty::factory()->create(['name' => 'Mata', 'sort_order' => 2]);
        $b = Specialty::factory()->create(['name' => 'Bedah', 'sort_order' => 1]);
        $a = Specialty::factory()->create(['name' => 'Anak', 'sort_order' => 1]);

        $this->assertSame([$a->id, $b->id, $c->id], Specialty::query()->ordered()->pluck('id')->all());
    }

    public function test_description_is_stored_as_plain_text_without_sanitizing(): void
    {
        $specialty = Specialty::factory()->create(['description' => 'tekanan <90 dan >100']);

        $this->assertSame('tekanan <90 dan >100', $specialty->fresh()->description);
    }
}
