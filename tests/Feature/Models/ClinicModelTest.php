<?php

namespace Tests\Feature\Models;

use App\Enums\ClinicType;
use App\Models\Clinic;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_follows_the_blueprint(): void
    {
        $this->assertSame([
            'name', 'slug', 'code', 'type', 'is_bookable', 'specialty_id', 'short_description',
            'description', 'location', 'is_active', 'sort_order', 'meta_title', 'meta_description',
        ], (new Clinic)->getFillable());
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        Clinic::factory()->make()->fill(['bukan_kolom' => 'x']);
    }

    public function test_casts_and_database_defaults(): void
    {
        $created = Clinic::create([
            'name' => 'Poliklinik Anak', 'slug' => 'poliklinik-anak', 'code' => 'ANK', 'type' => 'poliklinik',
            'short_description' => 'Layanan anak.', 'location' => 'Gedung A Lt. 1',
        ]);
        $clinic = Clinic::query()->findOrFail($created->id);

        $this->assertSame(ClinicType::Poliklinik, $clinic->type);
        $this->assertTrue($clinic->is_bookable);
        $this->assertTrue($clinic->is_active);
        $this->assertSame(0, $clinic->sort_order);
        $this->assertNull($clinic->specialty_id);
        $this->assertNull($clinic->description);
    }

    public function test_code_mutator_uppercases_and_trims(): void
    {
        $clinic = Clinic::factory()->create(['code' => ' ank ']);

        $this->assertSame('ANK', $clinic->code);
        $this->assertSame('ANK', $clinic->fresh()->code);

        $clinic->update(['code' => 'pd']);
        $this->assertSame('PD', $clinic->fresh()->code);
    }

    public function test_factory_states(): void
    {
        $plain = Clinic::factory()->create();
        $inactive = Clinic::factory()->inactive()->create();
        $support = Clinic::factory()->support()->create();

        $this->assertTrue($plain->is_active && $plain->is_bookable);
        $this->assertSame(ClinicType::Poliklinik, $plain->type);
        $this->assertFalse($inactive->is_active);
        $this->assertSame(ClinicType::Penunjang, $support->type);
        $this->assertFalse($support->is_bookable);
        $this->assertTrue($support->is_active);
        $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $plain->code);
    }

    public function test_active_bookable_and_ordered_scopes(): void
    {
        $b = Clinic::factory()->create(['name' => 'Poliklinik B', 'sort_order' => 1]);
        $a = Clinic::factory()->create(['name' => 'Poliklinik A', 'sort_order' => 1]);
        $c = Clinic::factory()->create(['name' => 'Poliklinik C', 'sort_order' => 0]);
        $off = Clinic::factory()->inactive()->create();
        $support = Clinic::factory()->support()->create();
        $inactiveBookable = Clinic::factory()->inactive()->create(['is_bookable' => true]);

        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id, $c->id, $support->id],
            Clinic::query()->active()->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id, $c->id],
            Clinic::query()->bookable()->pluck('id')->all(),
        );
        $this->assertNotContains($off->id, Clinic::query()->bookable()->pluck('id')->all());
        $this->assertNotContains($inactiveBookable->id, Clinic::query()->bookable()->pluck('id')->all());
        $this->assertSame(
            [$c->id, $a->id, $b->id],
            Clinic::query()->bookable()->ordered()->pluck('id')->all(),
        );
    }

    public function test_seo_accessors_fall_back_to_name_and_short_description(): void
    {
        $plain = Clinic::factory()->create(['name' => 'Poliklinik Anak', 'short_description' => 'Layanan anak.']);
        $custom = Clinic::factory()->create(['meta_title' => 'Judul SEO', 'meta_description' => 'Deskripsi SEO']);
        $blank = Clinic::factory()->create(['name' => 'Poliklinik Mata', 'short_description' => 'Layanan mata.', 'meta_title' => '', 'meta_description' => '']);

        $this->assertSame('Poliklinik Anak', $plain->seo_title);
        $this->assertSame('Layanan anak.', $plain->seo_description);
        $this->assertSame('Judul SEO', $custom->seo_title);
        $this->assertSame('Deskripsi SEO', $custom->seo_description);
        $this->assertSame('Poliklinik Mata', $blank->seo_title);
        $this->assertSame('Layanan mata.', $blank->seo_description);
    }

    public function test_specialty_relation_and_set_null_on_specialty_delete(): void
    {
        $specialty = Specialty::factory()->create();
        $clinic = Clinic::factory()->create(['specialty_id' => $specialty->id]);

        $this->assertTrue($clinic->fresh()->load('specialty')->specialty->is($specialty));
        $this->assertSame([$clinic->id], $specialty->clinics()->pluck('id')->all());

        $specialty->delete();

        $this->assertNull(Clinic::query()->find($clinic->id)->specialty_id);
    }

    public function test_relations_are_not_lazy_loaded_in_strict_mode(): void
    {
        $specialty = Specialty::factory()->create();
        Clinic::factory()->count(2)->create(['specialty_id' => $specialty->id]);

        $clinics = Clinic::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $clinics->each(fn (Clinic $clinic) => $clinic->specialty);
    }
}
