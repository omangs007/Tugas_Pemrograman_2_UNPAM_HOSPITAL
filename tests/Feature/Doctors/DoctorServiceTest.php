<?php

namespace Tests\Feature\Doctors;

use App\Enums\ActorType;
use App\Enums\DoctorStatus;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\User;
use App\Services\Doctors\DoctorService;
use App\Support\Actor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use RuntimeException;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class DoctorServiceTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    private DoctorService $service;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = $this->app->make(DoctorService::class);
        $this->staff = $this->createStaff('content-admin');
    }

    // ------------------------------------------------------------------ pembantu

    private function actor(): Actor
    {
        return Actor::staff($this->staff);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $clinic = $this->createClinic();
        $first = $this->createSpecialty();
        $second = $this->createSpecialty();

        return array_merge([
            'name' => 'Andi Pratama',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.A',
            'gender' => 'L',
            'clinic_id' => $clinic->id,
            'short_bio' => 'Dokter spesialis anak.',
            'bio' => 'Biodata lengkap dokter.',
            'sort_order' => 5,
            'meta_title' => null,
            'meta_description' => null,
            'specialty_ids' => [$first->id, $second->id],
            'primary_specialty_id' => $second->id,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDoctor(array $overrides = []): Doctor
    {
        return $this->service->create($this->payload($overrides), $this->actor());
    }

    /**
     * @return list<int>
     */
    private function pivotIds(Doctor $doctor): array
    {
        return DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->orderBy('specialty_id')->pluck('specialty_id')->map(fn ($id) => (int) $id)->all();
    }

    private function pivotPrimary(Doctor $doctor): ?int
    {
        $id = DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->where('is_primary', 1)->value('specialty_id');

        return $id === null ? null : (int) $id;
    }

    private function assertInvariants(): void
    {
        // K-5 (I-5): tepat satu spesialisasi utama per dokter. K-23 (I-23): archived <=> archived_at terisi.
        $this->assertSame([], DB::select('SELECT d.id FROM doctors d LEFT JOIN doctor_specialties ds ON ds.doctor_id = d.id AND ds.is_primary = 1 GROUP BY d.id HAVING COUNT(ds.doctor_id) <> 1'));
        $this->assertSame([], DB::select("SELECT id FROM doctors WHERE (status = 'archived') <> (archived_at IS NOT NULL)"));
    }

    private function assertRejected(callable $callback, string $key, string $message): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->assertSame([$message], $e->errors()[$key] ?? null);

            return;
        }

        $this->fail('ValidationException tidak dilempar.');
    }

    private function auditRows(string $action): Collection
    {
        return AuditLog::query()->where('action', $action)->orderBy('id')->get();
    }

    // ------------------------------------------------------------------ bentuk kelas

    public function test_service_is_final_and_resolvable_with_the_documented_constructor(): void
    {
        $class = new ReflectionClass(DoctorService::class);

        $this->assertTrue($class->isFinal());
        $this->assertSame(['audit', 'media'], array_map(fn ($parameter) => $parameter->getName(), $class->getConstructor()->getParameters()));
    }

    // ------------------------------------------------------------------ create

    public function test_create_makes_an_active_doctor_with_automatic_slug_and_ignores_status_slug_and_archived_at(): void
    {
        $data = $this->payload(['status' => 'archived', 'slug' => 'diretas', 'archived_at' => now()]);

        $doctor = $this->service->create($data, $this->actor());
        $fresh = Doctor::query()->findOrFail($doctor->id);

        $this->assertSame(DoctorStatus::Active, $fresh->status);
        $this->assertNull($fresh->archived_at);
        $this->assertSame('andi-pratama', $fresh->slug);
        $this->assertSame('Andi Pratama', $fresh->name);
        $this->assertSame('dr. Andi Pratama, Sp.A', $fresh->displayName);
        $this->assertSame(5, $fresh->sort_order);
        $this->assertSame($data['clinic_id'], $fresh->clinic_id);
        $this->assertInvariants();
    }

    public function test_create_stores_specialties_with_exactly_the_requested_primary(): void
    {
        $data = $this->payload();

        $doctor = $this->service->create($data, $this->actor());

        $ids = $data['specialty_ids'];
        sort($ids);
        $this->assertSame($ids, $this->pivotIds($doctor));
        $this->assertSame($data['primary_specialty_id'], $this->pivotPrimary($doctor));
        $this->assertSame(1, DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->where('is_primary', 1)->count());
    }

    public function test_create_defaults_sort_order_and_normalises_blank_optional_texts(): void
    {
        $doctor = $this->service->create($this->payload(['sort_order' => null, 'title_prefix' => '  ', 'short_bio' => '', 'name' => '  Budi Santoso  ']), $this->actor());
        $fresh = Doctor::query()->findOrFail($doctor->id);

        $this->assertSame(0, $fresh->sort_order);
        $this->assertNull($fresh->title_prefix);
        $this->assertNull($fresh->short_bio);
        $this->assertSame('Budi Santoso', $fresh->name);
    }

    public function test_two_doctors_with_the_same_name_get_distinct_slugs(): void
    {
        $first = $this->makeDoctor();
        $second = $this->makeDoctor();

        $this->assertSame('andi-pratama', $first->slug);
        $this->assertSame('andi-pratama-2', $second->slug);
    }

    public function test_create_writes_the_doctor_created_audit_row_with_the_documented_shape(): void
    {
        $data = $this->payload();

        $doctor = $this->service->create($data, $this->actor());

        $log = $this->auditRows('doctor.created')->sole();
        $ids = $data['specialty_ids'];
        sort($ids);

        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->staff->id, (int) $log->user_id);
        $this->assertSame((new Doctor)->getMorphClass(), $log->auditable_type);
        $this->assertSame($doctor->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertEquals([
            'name' => 'Andi Pratama',
            'clinic_id' => $data['clinic_id'],
            'status' => 'active',
            'specialty_ids' => $ids,
        ], $log->new_values);
        $this->assertStringContainsString('dr. Andi Pratama, Sp.A', $log->description);
    }

    public function test_create_with_a_photo_stores_it_for_the_doctor_with_alt_text_and_uploader(): void
    {
        $doctor = $this->service->create($this->payload(), $this->actor(), ImageFixtures::jpeg(800, 800), 'Foto dr. Andi');

        $media = Media::query()->where('collection', 'photo')->sole();

        $this->assertSame((new Doctor)->getMorphClass(), $media->model_type);
        $this->assertSame($doctor->id, (int) $media->model_id);
        $this->assertSame('Foto dr. Andi', $media->alt_text);
        $this->assertSame($this->staff->id, (int) $media->uploaded_by);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
        $this->assertSame(1, $this->auditRows('doctor.created')->count());
        $this->assertSame(1, $this->auditRows('media.uploaded')->count());
    }

    public function test_create_is_rolled_back_completely_when_the_photo_is_rejected(): void
    {
        $this->assertRejected(
            fn () => $this->service->create($this->payload(), $this->actor(), ImageFixtures::corruptPng(600, 600), 'Rusak'),
            'file',
            'File tidak dapat diproses sebagai gambar yang valid.',
        );

        $this->assertRejected(
            fn () => $this->service->create($this->payload(), $this->actor(), ImageFixtures::jpeg(200, 200), 'Terlalu kecil'),
            'file',
            'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.',
        );

        $this->assertRejected(
            fn () => $this->service->create($this->payload(), $this->actor(), ImageFixtures::jpeg(800, 800), null),
            'alt_text',
            'Teks alternatif gambar wajib diisi.',
        );

        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame(0, DB::table('doctor_specialties')->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, $this->auditRows('doctor.created')->count());
    }

    public function test_create_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        $data = $this->payload();

        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->service->create($data, $this->actor());
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame(0, DB::table('doctor_specialties')->count());
    }

    public function test_create_validates_specialties_before_writing_anything(): void
    {
        $extra = array_map(fn () => $this->createSpecialty()->id, range(1, 4));
        $base = $this->payload();
        [$one, $two] = $base['specialty_ids'];

        $this->assertRejected(fn () => $this->service->create(array_merge($base, ['specialty_ids' => []]), $this->actor()), 'specialty_ids', 'Pilih minimal satu spesialisasi.');
        $this->assertRejected(fn () => $this->service->create(array_merge($base, ['specialty_ids' => [$one, $two, ...$extra]]), $this->actor()), 'specialty_ids', 'Spesialisasi maksimal 5.');
        $this->assertRejected(fn () => $this->service->create(array_merge($base, ['specialty_ids' => [$one, 999999], 'primary_specialty_id' => $one]), $this->actor()), 'specialty_ids', 'Spesialisasi yang dipilih tidak tersedia.');
        $this->assertRejected(fn () => $this->service->create(array_merge($base, ['primary_specialty_id' => $extra[0]]), $this->actor()), 'primary_specialty_id', 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.');

        $withoutPrimary = $base;
        unset($withoutPrimary['primary_specialty_id']);
        $this->assertRejected(fn () => $this->service->create($withoutPrimary, $this->actor()), 'primary_specialty_id', 'Spesialisasi utama wajib dipilih.');

        $this->assertSame(0, Doctor::query()->count());
    }

    // ------------------------------------------------------------------ syncSpecialties

    public function test_sync_specialties_replaces_the_set_keeps_one_primary_and_ignores_duplicates(): void
    {
        $doctor = $this->makeDoctor();
        $a = $this->createSpecialty();
        $b = $this->createSpecialty();

        $this->service->syncSpecialties($doctor, [$a->id, $b->id, $b->id], $a->id);

        $this->assertSame([$a->id, $b->id], $this->pivotIds($doctor));
        $this->assertSame($a->id, $this->pivotPrimary($doctor));

        $this->service->syncSpecialties($doctor, [$b->id, $a->id], $b->id);

        $this->assertSame($b->id, $this->pivotPrimary($doctor));
        $this->assertSame(1, DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->where('is_primary', 1)->count());

        $this->service->syncSpecialties($doctor, [$b->id], $b->id);

        $this->assertSame([$b->id], $this->pivotIds($doctor));
        $this->assertInvariants();
    }

    public function test_sync_specialties_accepts_inactive_specialties_already_attached(): void
    {
        // K4: service hanya memeriksa keberadaan; syarat "aktif atau sudah terpasang" ada di Form Request (Task 11).
        $doctor = $this->makeDoctor();
        $inactive = $this->createSpecialty(['is_active' => false]);
        $active = $this->createSpecialty();

        $this->service->syncSpecialties($doctor, [$inactive->id, $active->id], $inactive->id);

        $this->assertSame([$inactive->id, $active->id], $this->pivotIds($doctor));
        $this->assertSame($inactive->id, $this->pivotPrimary($doctor));
        $this->assertInvariants();
    }

    public function test_sync_specialties_rejects_invalid_input_and_leaves_the_pivot_untouched(): void
    {
        $doctor = $this->makeDoctor();
        $before = $this->pivotIds($doctor);
        $primary = $this->pivotPrimary($doctor);
        $other = $this->createSpecialty();

        $this->assertRejected(fn () => $this->service->syncSpecialties($doctor, [], 1), 'specialty_ids', 'Pilih minimal satu spesialisasi.');
        $this->assertRejected(fn () => $this->service->syncSpecialties($doctor, [$before[0]], $other->id), 'primary_specialty_id', 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.');

        $this->assertSame($before, $this->pivotIds($doctor));
        $this->assertSame($primary, $this->pivotPrimary($doctor));
    }

    // ---- akhir siklus A
}
