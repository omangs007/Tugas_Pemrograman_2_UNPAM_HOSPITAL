<?php

namespace Tests\Feature\Auth;

use App\Models\Article;
use App\Models\Banner;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\Media;
use App\Models\Page;
use App\Models\Specialty;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F2-R16 / Review Focus 5: akses media lintas pemilik.
 */
class MediaPolicyTest extends TestCase
{
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ROLES = ['super-admin', 'content-admin', 'registration-officer', 'patient'];

    /**
     * Pemilik: [model_type, koleksi, izin yang dibutuhkan, peran yang diizinkan].
     *
     * @return array<string, array{0: ?string, 1: string, 2: array<string, bool>}>
     */
    public static function ownerCases(): array
    {
        $contentOnly = ['super-admin' => true, 'content-admin' => true, 'registration-officer' => false, 'patient' => false];
        $superOnly = ['super-admin' => true, 'content-admin' => false, 'registration-officer' => false, 'patient' => false];

        return [
            'foto dokter' => [Doctor::class, 'photo', $contentOnly],
            'cover poliklinik' => [Clinic::class, 'cover', $contentOnly],
            'galeri poliklinik' => [Clinic::class, 'gallery', $contentOnly],
            'media spesialisasi' => [Specialty::class, 'cover', $contentOnly],
            'gambar editor halaman' => [Page::class, 'editor', $contentOnly],
            'cover artikel' => [Article::class, 'cover', $contentOnly],
            'gambar editor artikel' => [Article::class, 'editor', $contentOnly],
            'gambar banner' => [Banner::class, 'image', $contentOnly],
            'foto album galeri' => [Gallery::class, 'photos', $contentOnly],
            'logo mitra asuransi' => [InsurancePartner::class, 'logo', $contentOnly],
            'logo situs (tanpa pemilik)' => [null, 'logo', $superOnly],
            'tipe pemilik yang belum dipetakan' => ['App\Models\Facility', 'cover', $superOnly],
            'koleksi tanpa pemilik selain logo dan editor' => [null, 'photo', $superOnly],
        ];
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('ownerCases')]
    public function test_update_and_delete_follow_the_owner_type(?string $type, string $collection, array $expected): void
    {
        $media = $this->mediaFor($type, $collection);

        foreach ($expected as $role => $allowed) {
            $user = $this->userFor($role);

            foreach (['update', 'delete'] as $ability) {
                $this->assertSame($allowed, Gate::forUser($user)->allows($ability, $media), "{$role} -> {$ability} {$collection}");
            }
        }
    }

    public function test_reorder_is_only_for_media_that_has_an_owner(): void
    {
        $owned = $this->mediaFor(Clinic::class, 'gallery');
        $unowned = $this->mediaFor(null, 'editor');
        $content = $this->userFor('content-admin');

        $this->assertTrue(Gate::forUser($content)->allows('reorder', $owned));
        $this->assertFalse(Gate::forUser($this->userFor('registration-officer'))->allows('reorder', $owned));
        $this->assertFalse(Gate::forUser($this->userFor('patient'))->allows('reorder', $owned));
        $this->assertFalse(Gate::forUser($content)->allows('reorder', $unowned));
        $this->assertTrue(Gate::forUser($content)->allows('reorder', $this->mediaFor(Doctor::class, 'photo')));
    }

    public function test_reorder_of_content_owned_media_is_for_content_managers_only(): void
    {
        $content = $this->userFor('content-admin');
        $officer = $this->userFor('registration-officer');
        $patient = $this->userFor('patient');

        foreach ([[Gallery::class, 'photos'], [Article::class, 'cover'], [Banner::class, 'image'], [InsurancePartner::class, 'logo'], [Page::class, 'editor']] as [$type, $collection]) {
            $media = $this->mediaFor($type, $collection);

            $this->assertTrue(Gate::forUser($content)->allows('reorder', $media), "content-admin {$collection}");
            $this->assertTrue(Gate::forUser($this->userFor('super-admin'))->allows('reorder', $media), "super-admin {$collection}");
            $this->assertFalse(Gate::forUser($officer)->allows('reorder', $media), "petugas {$collection}");
            $this->assertFalse(Gate::forUser($patient)->allows('reorder', $media), "pasien {$collection}");
        }
    }

    public function test_content_admin_cannot_touch_the_site_logo_but_super_admin_can(): void
    {
        $logo = $this->mediaFor(null, 'logo');

        $this->assertFalse(Gate::forUser($this->userFor('content-admin'))->allows('update', $logo));
        $this->assertFalse(Gate::forUser($this->userFor('content-admin'))->allows('delete', $logo));
        $this->assertTrue(Gate::forUser($this->userFor('super-admin'))->allows('update', $logo));
    }

    public function test_registration_officer_cannot_delete_a_doctor_photo(): void
    {
        $photo = $this->mediaFor(Doctor::class, 'photo');

        $this->assertFalse(Gate::forUser($this->userFor('registration-officer'))->allows('delete', $photo));
        $this->assertFalse(Gate::forUser($this->userFor('registration-officer'))->allows('update', $photo));
    }

    public function test_editor_media_without_owner_can_be_managed_by_its_uploader_or_a_content_group_holder(): void
    {
        $officer = $this->userFor('registration-officer');
        $otherOfficer = $this->userFor('registration-officer');
        $content = $this->userFor('content-admin');
        $media = $this->mediaFor(null, 'editor', $officer->id);

        $this->assertTrue(Gate::forUser($officer)->allows('delete', $media), 'Pengunggah sendiri.');
        $this->assertTrue(Gate::forUser($officer)->allows('update', $media));
        $this->assertTrue(Gate::forUser($content)->allows('delete', $media), 'Pemegang content.manage.');
        $this->assertFalse(Gate::forUser($otherOfficer)->allows('delete', $media), 'Staf lain tanpa izin kelompok.');
        $this->assertFalse(Gate::forUser($otherOfficer)->allows('update', $media));
    }

    public function test_a_patient_never_manages_media_even_if_marked_as_uploader(): void
    {
        $patient = $this->userFor('patient');
        $media = $this->mediaFor(null, 'editor', $patient->id);

        $this->assertFalse(Gate::forUser($patient)->allows('delete', $media));
        $this->assertFalse(Gate::forUser($patient)->allows('update', $media));
        $this->assertFalse(Gate::forUser($patient)->allows('upload', Media::class));
    }

    public function test_upload_requires_one_of_the_content_group_permissions(): void
    {
        foreach (['super-admin' => true, 'content-admin' => true, 'registration-officer' => false, 'patient' => false] as $role => $allowed) {
            $this->assertSame($allowed, Gate::forUser($this->userFor($role))->allows('upload', Media::class), $role);
        }
    }

    public function test_policy_is_discovered_automatically(): void
    {
        $this->assertInstanceOf(MediaPolicy::class, Gate::getPolicyFor(Media::class));
    }

    private function mediaFor(?string $type, string $collection, ?int $uploadedBy = null): Media
    {
        return $this->createMediaRow([
            'model_type' => $type,
            'model_id' => $type === null ? null : 1,
            'collection' => $collection,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    private function userFor(string $role): User
    {
        return $role === 'patient'
            ? User::factory()->create()
            : User::query()->findOrFail($this->createStaff($role)->id);
    }
}
