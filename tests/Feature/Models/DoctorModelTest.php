<?php

namespace Tests\Feature\Models;

use App\Enums\DoctorStatus;
use App\Enums\Gender;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class DoctorModelTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    public function test_fillable_follows_the_blueprint_and_excludes_archived_at(): void
    {
        $this->assertSame([
            'clinic_id', 'name', 'slug', 'title_prefix', 'title_suffix', 'gender', 'short_bio', 'bio',
            'status', 'sort_order', 'meta_title', 'meta_description',
        ], (new Doctor)->getFillable());

        $this->expectException(MassAssignmentException::class);

        Doctor::factory()->make()->fill(['archived_at' => now()]);
    }

    public function test_casts(): void
    {
        $doctor = Doctor::query()->findOrFail($this->createDoctor(['gender' => 'P'])->id);

        $this->assertSame(Gender::Female, $doctor->gender);
        $this->assertSame(DoctorStatus::Active, $doctor->status);
        $this->assertNull($doctor->archived_at);
        $this->assertSame(0, $doctor->sort_order);

        $archived = Doctor::query()->findOrFail(Doctor::factory()->archived()->create()->id);

        $this->assertSame(DoctorStatus::Archived, $archived->status);
        $this->assertInstanceOf(CarbonInterface::class, $archived->archived_at);
    }

    /** @return array<string, array{0: ?string, 1: string, 2: ?string, 3: string}> CRD-04 */
    public static function displayNames(): array
    {
        return [
            'gelar depan dan belakang' => ['dr.', 'Laras Wulandari', 'Sp.A', 'dr. Laras Wulandari, Sp.A'],
            'tanpa sufiks' => ['dr.', 'Rani Oktaviani', null, 'dr. Rani Oktaviani'],
            'sufiks kosong' => ['drg.', 'Ayu Larasati Hakim', '', 'drg. Ayu Larasati Hakim'],
            'tanpa gelar depan' => [null, 'Eko Susanto', 'Sp.B', 'Eko Susanto, Sp.B'],
            'tanpa gelar sama sekali' => [null, 'Eko Susanto', null, 'Eko Susanto'],
            'spasi berlebih' => [' dr. ', ' Eko Susanto ', ' Sp.B ', 'dr. Eko Susanto, Sp.B'],
            'sufiks bertanda' => ['dr.', 'Yoga Prasetya', 'Sp.THT-KL', 'dr. Yoga Prasetya, Sp.THT-KL'],
        ];
    }

    #[DataProvider('displayNames')]
    public function test_display_name(?string $prefix, string $name, ?string $suffix, string $expected): void
    {
        $doctor = Doctor::factory()->make(['title_prefix' => $prefix, 'name' => $name, 'title_suffix' => $suffix]);

        $this->assertSame($expected, $doctor->display_name);
        $this->assertSame($expected, $doctor->displayName);
    }

    public function test_primary_specialty_uses_the_primary_pivot_flag_with_first_as_fallback(): void
    {
        $general = $this->createSpecialty(['name' => 'Penyakit Dalam']);
        $heart = $this->createSpecialty(['name' => 'Jantung']);

        $doctor = Doctor::factory()->create();
        $doctor->specialties()->attach($general->id, ['is_primary' => false]);
        $doctor->specialties()->attach($heart->id, ['is_primary' => true]);

        $this->assertTrue(Doctor::query()->with('specialties')->find($doctor->id)->primary_specialty->is($heart));

        $noPrimary = Doctor::factory()->create();
        $noPrimary->specialties()->attach([$general->id => ['is_primary' => false]]);

        $this->assertTrue(Doctor::query()->with('specialties')->find($noPrimary->id)->primary_specialty->is($general));

        $none = Doctor::factory()->create();

        $this->assertNull(Doctor::query()->with('specialties')->find($none->id)->primary_specialty);
    }

    public function test_primary_specialty_is_not_cached_across_reloads_of_the_relation(): void
    {
        $first = $this->createSpecialty();
        $second = $this->createSpecialty();
        $doctor = $this->createDoctor([], null, [$first, $second]);

        $this->assertTrue($doctor->primary_specialty->is($first));

        $doctor->specialties()->updateExistingPivot($first->id, ['is_primary' => false]);
        $doctor->specialties()->updateExistingPivot($second->id, ['is_primary' => true]);
        $doctor->load('specialties');

        $this->assertTrue($doctor->primary_specialty->is($second));
    }

    public function test_primary_specialty_requires_the_loaded_relation_in_strict_mode(): void
    {
        $this->createDoctor();
        $this->createDoctor();
        $doctors = Doctor::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $doctors->first()->primary_specialty;
    }

    public function test_list_with_eager_loading_is_safe_for_several_rows_in_strict_mode(): void
    {
        foreach (range(1, 4) as $i) {
            $this->createDoctor([], null, [$this->createSpecialty(), $this->createSpecialty()]);
        }

        $names = Doctor::query()->with(['clinic', 'specialties'])->get()->map(
            fn (Doctor $doctor): string => $doctor->display_name.'|'.$doctor->clinic->name.'|'.$doctor->primary_specialty->name,
        );

        $this->assertCount(4, $names);
    }

    public function test_relations_without_eager_loading_are_rejected_for_several_rows(): void
    {
        $this->createDoctor();
        $this->createDoctor();
        $doctors = Doctor::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $doctors->each(fn (Doctor $doctor) => $doctor->clinic);
    }

    public function test_active_and_publicly_visible_scopes(): void
    {
        $activeClinic = $this->createClinic();
        $inactiveClinic = $this->createClinic(['is_active' => false]);

        $visible = $this->createDoctor([], $activeClinic);
        $inactiveDoctor = $this->createDoctor(['status' => DoctorStatus::Inactive], $activeClinic);
        $archived = Doctor::factory()->archived()->create(['clinic_id' => $activeClinic->id]);
        $inInactiveClinic = $this->createDoctor([], $inactiveClinic);

        $this->assertEqualsCanonicalizing(
            [$visible->id, $inInactiveClinic->id],
            Doctor::query()->active()->pluck('id')->all(),
        );
        $this->assertSame([$visible->id], Doctor::query()->publiclyVisible()->pluck('id')->all());
        $this->assertNotContains($inactiveDoctor->id, Doctor::query()->publiclyVisible()->pluck('id')->all());
        $this->assertNotContains($archived->id, Doctor::query()->publiclyVisible()->pluck('id')->all());
    }

    public function test_search_matches_doctor_name_or_specialty_name_and_treats_wildcards_literally(): void
    {
        $urology = $this->createSpecialty(['name' => 'Urologi']);
        $pediatrics = $this->createSpecialty(['name' => 'Anak']);

        $raka = $this->createDoctor(['name' => 'Raka Pradipta'], null, [$urology]);
        $laras = $this->createDoctor(['name' => 'Laras Wulandari'], null, [$pediatrics]);
        $percent = $this->createDoctor(['name' => 'Diskon 100% Sehat'], null, [$pediatrics]);

        $this->assertSame([$raka->id], Doctor::query()->search('raka')->pluck('id')->all());
        $this->assertSame([$raka->id], Doctor::query()->search('urolog')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$laras->id, $percent->id], Doctor::query()->search('Anak')->pluck('id')->all());
        $this->assertSame([$percent->id], Doctor::query()->search('100%')->pluck('id')->all());
        $this->assertSame([], Doctor::query()->search('%')->where('name', 'Raka Pradipta')->pluck('id')->all());
        $this->assertCount(3, Doctor::query()->search(null)->get());
        $this->assertCount(3, Doctor::query()->search('   ')->get());
    }

    public function test_in_specialty_accepts_id_or_slug_and_matches_secondary_specialties(): void
    {
        $internal = $this->createSpecialty(['name' => 'Penyakit Dalam', 'slug' => 'penyakit-dalam']);
        $heart = $this->createSpecialty(['name' => 'Jantung', 'slug' => 'jantung']);

        $sekar = $this->createDoctor(['name' => 'Sekar'], null, [$internal]);
        $maya = $this->createDoctor(['name' => 'Maya'], null, [$heart, $internal]);
        $fajar = $this->createDoctor(['name' => 'Fajar'], null, [$heart]);

        $this->assertEqualsCanonicalizing([$sekar->id, $maya->id], Doctor::query()->inSpecialty('penyakit-dalam')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$sekar->id, $maya->id], Doctor::query()->inSpecialty($internal->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$maya->id, $fajar->id], Doctor::query()->inSpecialty('jantung')->pluck('id')->all());
        $this->assertSame([], Doctor::query()->inSpecialty('tidak-ada')->pluck('id')->all());
        $this->assertSame([], Doctor::query()->inSpecialty((string) $internal->id)->pluck('id')->all(), 'String dibaca sebagai slug, bukan id.');
    }

    public function test_in_clinic_and_ordered_scopes(): void
    {
        $clinicA = $this->createClinic();
        $clinicB = $this->createClinic();

        $z = $this->createDoctor(['name' => 'Zaki', 'sort_order' => 1], $clinicA);
        $a = $this->createDoctor(['name' => 'Adi', 'sort_order' => 1], $clinicA);
        $first = $this->createDoctor(['name' => 'Yusuf', 'sort_order' => 0], $clinicB);

        $this->assertEqualsCanonicalizing([$z->id, $a->id], Doctor::query()->inClinic($clinicA->id)->pluck('id')->all());
        $this->assertSame([$first->id, $a->id, $z->id], Doctor::query()->ordered()->pluck('id')->all());
    }

    public function test_factory_with_specialties_marks_exactly_the_first_as_primary(): void
    {
        $doctor = Doctor::factory()->withSpecialties(3)->create();

        $pivot = DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->orderBy('specialty_id')->get();

        $this->assertCount(3, $pivot);
        $this->assertSame(1, $pivot->where('is_primary', 1)->count());
        $this->assertSame((int) $pivot->first()->specialty_id, (int) $pivot->firstWhere('is_primary', 1)->specialty_id);
        $this->assertSame(1, Doctor::factory()->withSpecialties()->create()->specialties()->count());
    }

    public function test_factory_states_and_dictionary_invariant_i23(): void
    {
        $inactive = Doctor::factory()->inactive()->create();
        $archived = Doctor::factory()->archived()->create();
        $active = Doctor::factory()->create();

        $this->assertSame(DoctorStatus::Inactive, $inactive->fresh()->status);
        $this->assertNull($inactive->fresh()->archived_at);
        $this->assertSame(DoctorStatus::Archived, $archived->fresh()->status);
        $this->assertNotNull($archived->fresh()->archived_at);
        $this->assertSame(
            0,
            Doctor::query()->whereRaw("(status = 'archived') <> (archived_at IS NOT NULL)")->count(),
            'K-23: status archived <=> archived_at terisi.',
        );
        $this->assertSame(DoctorStatus::Active, $active->fresh()->status);
    }

    public function test_deleting_a_clinic_that_has_doctors_is_rejected_for_the_model_too(): void
    {
        $doctor = $this->createDoctor();

        $this->expectException(QueryException::class);

        $doctor->clinic()->first()->delete();
    }

    public function test_clinic_deletion_blocked_reason(): void
    {
        $empty = $this->createClinic();
        $used = $this->createDoctor()->clinic()->first();

        $this->assertNull($empty->deletionBlockedReason());
        $this->assertSame(
            'Poliklinik ini masih dirujuk oleh data dokter sehingga tidak dapat dihapus. Nonaktifkan saja.',
            $used->deletionBlockedReason(),
        );
    }

    public function test_specialty_deletion_blocked_reason_for_doctors_and_clinics(): void
    {
        $free = $this->createSpecialty();
        $usedByDoctor = $this->createSpecialty();
        $usedByClinic = $this->createSpecialty();
        $this->createDoctor([], null, [$usedByDoctor]);
        $this->createClinic(['specialty_id' => $usedByClinic->id]);

        $message = 'Spesialisasi ini masih dipakai oleh dokter atau poliklinik sehingga tidak dapat dihapus. Nonaktifkan saja.';

        $this->assertNull($free->deletionBlockedReason());
        $this->assertSame($message, $usedByDoctor->deletionBlockedReason());
        $this->assertSame($message, $usedByClinic->deletionBlockedReason());
    }

    public function test_specialty_doctors_relation_exposes_the_pivot_flag(): void
    {
        $specialty = $this->createSpecialty();
        $primary = $this->createDoctor([], null, [$specialty]);
        $secondary = $this->createDoctor([], null, [$this->createSpecialty(), $specialty]);

        $flags = $specialty->doctors()->get()->mapWithKeys(
            fn (Doctor $doctor): array => [$doctor->id => (bool) $doctor->pivot->is_primary],
        )->all();

        $this->assertSame([$primary->id => true, $secondary->id => false], $flags);
        $this->assertInstanceOf(Clinic::class, $primary->clinic()->first());
        $this->assertInstanceOf(Specialty::class, $primary->specialties->first());
    }
}
