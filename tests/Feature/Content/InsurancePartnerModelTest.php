<?php

namespace Tests\Feature\Content;

use App\Enums\PaymentType;
use App\Models\InsurancePartner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

class InsurancePartnerModelTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithSchedules;
    use RefreshDatabase;

    public function test_types_labels_and_fillable_follow_the_blueprint(): void
    {
        $this->assertSame(['asuransi', 'perusahaan'], InsurancePartner::TYPES);
        $this->assertSame(['asuransi' => 'Asuransi', 'perusahaan' => 'Perusahaan'], InsurancePartner::TYPE_LABELS);
        $this->assertSame(
            ['name', 'slug', 'type', 'description', 'website_url', 'is_active', 'sort_order'],
            (new InsurancePartner)->getFillable(),
        );
    }

    public function test_defaults_and_factory_states(): void
    {
        $plain = $this->createInsurancePartner();
        $inactive = InsurancePartner::factory()->inactive()->create();
        $company = InsurancePartner::factory()->company()->create();

        $this->assertSame('asuransi', $plain->type);
        $this->assertTrue($plain->is_active);
        $this->assertNull($plain->website_url);
        $this->assertFalse($inactive->is_active);
        $this->assertSame('perusahaan', $company->type);
    }

    public function test_active_and_ordered_scopes(): void
    {
        $c = $this->createInsurancePartner(['name' => 'C Mitra', 'sort_order' => 2]);
        $b = $this->createInsurancePartner(['name' => 'B Mitra', 'sort_order' => 1]);
        $a = $this->createInsurancePartner(['name' => 'A Mitra', 'sort_order' => 1]);
        $off = InsurancePartner::factory()->inactive()->create(['name' => 'Nonaktif', 'sort_order' => 0]);

        $this->assertSame([$a->id, $b->id, $c->id], InsurancePartner::query()->active()->ordered()->pluck('id')->all());
        $this->assertSame([$off->id, $a->id, $b->id, $c->id], InsurancePartner::query()->ordered()->pluck('id')->all());
    }

    public function test_type_label_and_logo_url(): void
    {
        $asuransi = $this->createInsurancePartner(['type' => 'asuransi']);
        $perusahaan = $this->createInsurancePartner(['type' => 'perusahaan']);

        $loaded = InsurancePartner::query()->with('media')->orderBy('id')->get();

        $this->assertSame('Asuransi', $loaded[0]->type_label);
        $this->assertSame('Perusahaan', $loaded[1]->type_label);
        $this->assertSame(asset('images/placeholders/logo.svg'), $loaded[0]->logo_url);
        $this->assertSame($asuransi->id, $loaded[0]->id);
        $this->assertSame($perusahaan->id, $loaded[1]->id);
    }

    public function test_slug_is_derived_from_the_name(): void
    {
        $first = $this->createInsurancePartner(['name' => 'Asuransi Contoh Sehat']);
        $second = $this->createInsurancePartner(['name' => 'Asuransi Contoh Sehat']);
        $explicit = $this->createInsurancePartner(['name' => 'PT Contoh Karya Mandiri', 'slug' => 'pt-contoh-karya-mandiri']);

        $this->assertSame('asuransi-contoh-sehat', $first->slug);
        $this->assertSame('asuransi-contoh-sehat-2', $second->slug);
        $this->assertSame('pt-contoh-karya-mandiri', $explicit->slug);
        $this->assertSame(170, strlen(InsurancePartner::uniqueSlug(str_repeat('a', 400))));
    }

    public function test_deletion_is_allowed_when_no_appointment_refers_to_the_partner(): void
    {
        $partner = $this->createInsurancePartner();

        $this->assertNull($partner->deletionBlockedReason());
        $this->assertCount(0, $partner->appointments);
    }

    public function test_deletion_is_blocked_with_the_br_180_text_and_the_count(): void
    {
        $partner = $this->createInsurancePartner();
        $other = $this->createInsurancePartner();
        $session = $this->createSession();

        $this->bookAppointment($session, overrides: ['insurance_partner_id' => $partner->id, 'payment_type' => PaymentType::Asuransi]);

        $this->assertSame('Mitra asuransi masih dipakai oleh 1 data. Nonaktifkan saja.', $partner->deletionBlockedReason());

        $this->bookAppointment($session, overrides: ['insurance_partner_id' => $partner->id, 'payment_type' => PaymentType::Asuransi]);
        $this->bookAppointment($session, overrides: ['insurance_partner_id' => $other->id, 'payment_type' => PaymentType::Asuransi]);

        $this->assertSame('Mitra asuransi masih dipakai oleh 2 data. Nonaktifkan saja.', $partner->deletionBlockedReason());
        $this->assertSame('Mitra asuransi masih dipakai oleh 1 data. Nonaktifkan saja.', $other->deletionBlockedReason());
        $this->assertCount(2, $partner->appointments);
    }

    public function test_the_database_also_rejects_deleting_a_referenced_partner(): void
    {
        $partner = $this->createInsurancePartner();
        $this->bookAppointment($this->createSession(), overrides: ['insurance_partner_id' => $partner->id, 'payment_type' => PaymentType::Asuransi]);

        $this->expectException(QueryException::class);

        $partner->delete();
    }
}
