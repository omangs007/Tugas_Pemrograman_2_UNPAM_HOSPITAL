<?php

namespace Tests\Feature\Patient;

use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\PaymentType;
use App\Enums\SessionStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Booking\BookingCodeGenerator;
use App\Services\Scheduling\PracticeSessionGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Langkah 5 (tinjau) dan simpan (F6-R9/R10/R20/R21/R37): VAL-03, VAL-08, BKG-20 (sisi HTTP), SEK-05/06/11/12/15,
 * Review Focus 1 (kirim ganda), 3 dan 4 (field tersembunyi dipalsukan), 5. Waktu: Senin 5 Oktober 2026 07.00; sesi uji
 * Selasa 6 Oktober 09.00 sampai 12.00, kuota 20.
 */
class BookingSubmissionTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const BPJS = '0009900000011';

    private ?User $patient = null;

    private ?PracticeSession $bookable = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 07:00:00');

        $today = CarbonImmutable::today();
        Cache::put(PracticeSessionGenerator::markerKey($today), true, $today->endOfDay());
    }

    // --- pembantu ----------------------------------------------------------------------------

    /** Pasien uji (satu per tes). Panggilan PERTAMA menentukan isi profil (mis. `bpjs_number` tersimpan). */
    private function patient(array $profile = []): User
    {
        return $this->patient ??= $this->createPatient(['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'], $profile + ['full_name' => 'Siti Rahma']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function sessionFor(Doctor $doctor, string $date, array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ], $overrides));
    }

    private function bookableSession(): PracticeSession
    {
        return $this->bookable ??= $this->sessionFor(
            $this->createDoctor(['name' => 'Laras Wulandari'], $this->createClinic(['name' => 'Poliklinik Anak Uji', 'code' => 'ANK'])),
            '2026-10-06',
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'practice_session_id' => (string) $this->bookableSession()->id,
            'payment_type' => 'umum',
            'complaint' => 'Batuk berdahak sejak tiga hari.',
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function submit(string $routeName, array $data, ?User $user = null): TestResponse
    {
        $id = $data['practice_session_id'] ?? null;
        $query = is_string($id) || is_int($id) ? ['sesi' => $id] : [];

        return $this->actingAs($user ?? $this->patient())
            ->from(route('patient.appointments.create', $query))
            ->post(route($routeName), $data);
    }

    private function wizardStepThree(PracticeSession $session): string
    {
        return route('patient.appointments.create', [
            'poli' => Clinic::query()->findOrFail($session->clinic_id)->slug,
            'dokter' => Doctor::query()->findOrFail($session->doctor_id)->slug,
        ]);
    }

    /**
     * Field tersembunyi form `store` pada halaman tinjau (tanpa `_token`), nilai sudah di-decode.
     *
     * @return array<string, string>
     */
    private function hiddenFields(string $html): array
    {
        preg_match_all('#<input type="hidden" name="([^"]+)" value="([^"]*)">#', $html, $matches, PREG_SET_ORDER);
        $fields = [];

        foreach ($matches as [, $name, $value]) {
            if ($name !== '_token') {
                $fields[$name] = html_entity_decode($value, ENT_QUOTES);
            }
        }

        ksort($fields);

        return $fields;
    }

    /** Memasang penghenti satu kali tepat sebelum transaksi `book()` dimulai (lihat Catatan teknis). */
    private function beforeBooking(callable $callback): void
    {
        $armed = true;

        DB::beforeStartingTransaction(function () use (&$armed, $callback): void {
            if ($armed) {
                $armed = false;
                $callback();
            }
        });
    }

    /** @return array<string, array{string}> */
    public static function submitRoutes(): array
    {
        return [
            'tinjau' => ['patient.appointments.review'],
            'simpan' => ['patient.appointments.store'],
        ];
    }

    // --- route -------------------------------------------------------------------------------

    public function test_the_routes_are_registered_with_the_documented_middleware(): void
    {
        $expected = [
            'patient.appointments.review' => ['pasien/pendaftaran/tinjau', ['POST'], ['web', 'auth', 'account:patient', 'active'], 'review'],
            'patient.appointments.store' => ['pasien/pendaftaran', ['POST'], ['web', 'auth', 'account:patient', 'active', 'throttle:booking'], 'store'],
            'patient.appointments.show' => ['pasien/pendaftaran/{appointment}', ['GET', 'HEAD'], ['web', 'auth', 'account:patient', 'active'], 'show'],
        ];

        foreach ($expected as $name => [$uri, $methods, $middleware, $method]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, $name);
            $this->assertSame($uri, $route->uri(), $name);
            $this->assertSame($methods, $route->methods(), $name);
            $this->assertSame($middleware, array_values(array_filter($route->gatherMiddleware(), 'is_string')), $name);
            $this->assertSame('App\\Http\\Controllers\\Patient\\AppointmentController@'.$method, $route->getActionName(), $name);
        }
    }

    public function test_guests_and_staff_cannot_use_review_and_store(): void
    {
        $data = $this->payload();
        $staff = $this->createStaff('registration-officer');

        foreach (['patient.appointments.review', 'patient.appointments.store'] as $name) {
            $this->post(route($name), $data)->assertRedirect(route('login'));
        }

        foreach (['patient.appointments.review', 'patient.appointments.store'] as $name) {
            $this->actingAs($staff)->post(route($name), $data)->assertRedirect(route('admin.dashboard'));
        }

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_the_step_four_form_posts_to_the_review_route(): void
    {
        $session = $this->bookableSession();

        $html = $this->actingAs($this->patient())->get(route('patient.appointments.create', ['sesi' => $session->id]))->assertOk()->getContent();

        $this->assertStringContainsString('<form method="POST" action="'.route('patient.appointments.review').'" novalidate>', $html);
    }

    // --- tinjau (langkah 5) ------------------------------------------------------------------

    public function test_review_renders_the_summary_without_saving_anything_or_assigning_a_queue_number(): void
    {
        $session = $this->bookableSession();

        $response = $this->submit('patient.appointments.review', $this->payload())->assertOk();
        $html = $response->getContent();

        foreach ([
            'Langkah 5 - Periksa dan kirim',
            'Poliklinik Anak Uji',
            'dr. Laras Wulandari, Sp.PD',
            'Selasa, 6 Oktober 2026',
            '09.00–12.00 (sisa kuota saat ini: 20 dari 20)',
            'Siti Rahma',
            'Umum/Pribadi',
            'Batuk berdahak sejak tiga hari.',
            'Nomor antrean diberikan setelah pendaftaran berhasil dikirim.',
            'Pembatalan oleh pasien dapat dilakukan hingga 120 menit sebelum sesi.',
            'Ubah data',
            'Kirim Pendaftaran',
        ] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }

        $this->assertStringContainsString('<form method="POST" action="'.route('patient.appointments.store').'" novalidate>', $html);
        $this->assertStringContainsString('href="'.route('patient.appointments.create', ['sesi' => $session->id]).'"', $html, 'Ubah data = GET ke langkah 4.');
        $this->assertSame(
            ['complaint' => 'Batuk berdahak sejak tiga hari.', 'payment_type' => 'umum', 'practice_session_id' => (string) $session->id],
            $this->hiddenFields($html),
        );
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->assertSame(0, Appointment::query()->count());
        $this->assertSame(0, $session->fresh()->booked_count);
        $this->assertSame(0, $session->fresh()->last_queue_number);
    }

    public function test_review_masks_a_typed_bpjs_number_and_carries_it_only_as_a_hidden_field(): void
    {
        $html = $this->submit('patient.appointments.review', $this->payload([
            'payment_type' => 'bpjs',
            'bpjs_number' => '000 9900-000011',
            'save_bpjs' => '1',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('BPJS Kesehatan', $html);
        $this->assertStringContainsString('•••••••••0011', $html);
        $this->assertSame(1, substr_count($html, self::BPJS), 'Nomor lengkap hanya ada sekali: field tersembunyi isian pasien sendiri.');
        $this->assertSame(
            ['bpjs_number' => self::BPJS, 'complaint' => 'Batuk berdahak sejak tiga hari.', 'payment_type' => 'bpjs', 'practice_session_id' => (string) $this->bookableSession()->id, 'save_bpjs' => '1'],
            $this->hiddenFields($html),
        );
    }

    public function test_review_never_renders_a_stored_bpjs_number_and_carries_no_hidden_number(): void
    {
        $this->patient(['bpjs_number' => self::BPJS]);

        $html = $this->submit('patient.appointments.review', $this->payload(['payment_type' => 'bpjs']))->assertOk()->getContent();

        $this->assertStringContainsString('•••••••••0011', $html);
        $this->assertStringNotContainsString(self::BPJS, $html);
        $this->assertArrayNotHasKey('bpjs_number', $this->hiddenFields($html));
        $this->assertArrayNotHasKey('save_bpjs', $this->hiddenFields($html));
    }

    public function test_review_shows_the_partner_and_a_masked_policy_number(): void
    {
        $partner = InsurancePartner::factory()->create(['name' => 'Asuransi Contoh Aktif']);

        $html = $this->submit('patient.appointments.review', $this->payload([
            'payment_type' => 'asuransi',
            'insurance_partner_id' => (string) $partner->id,
            'policy_number' => 'AB 123-45',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Asuransi Contoh Aktif', $html);
        $this->assertStringContainsString('•••••••••3-45', $html);
        $this->assertSame(1, substr_count($html, 'AB123-45'), 'Nomor polis lengkap hanya sebagai field tersembunyi isian pasien.');
        $this->assertSame('AB123-45', $this->hiddenFields($html)['policy_number']);
        $this->assertSame((string) $partner->id, $this->hiddenFields($html)['insurance_partner_id']);
    }

    /** SEK-15: keluhan berisi tag tampil sebagai teks dan nilai field tersembunyi di-escape. */
    public function test_review_escapes_the_complaint_in_text_and_in_the_hidden_field(): void
    {
        $complaint = '"><script>alert(1)</script>';

        $html = $this->submit('patient.appointments.review', $this->payload(['complaint' => $complaint]))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertSame($complaint, $this->hiddenFields($html)['complaint']);
    }

    public function test_review_is_not_throttled_but_store_is(): void
    {
        $this->assertNotContains('throttle:booking', Route::getRoutes()->getByName('patient.appointments.review')->gatherMiddleware());
        $this->assertContains('throttle:booking', Route::getRoutes()->getByName('patient.appointments.store')->gatherMiddleware());

        for ($attempt = 1; $attempt <= 12; $attempt++) {
            $this->submit('patient.appointments.review', $this->payload())->assertOk();
        }
    }

    // --- validasi (VAL-03, Review Focus 4) -----------------------------------------------------

    /**
     * Kolom: [override payload, field galat, pesan, sesi pada URL pengalihan (`__SESI__` | '' | id literal)].
     *
     * @return array<string, array{array<string, mixed>, string, string, string}>
     */
    public static function invalidSubmissions(): array
    {
        $bpjs = 'Nomor BPJS harus terdiri dari 13 digit angka.';
        $partner = 'Mitra asuransi yang dipilih tidak tersedia.';
        $session = 'Sesi praktik tidak ditemukan.';
        $payment = 'Jenis pembayaran tidak valid.';

        return [
            'bpjs 12 digit' => [['payment_type' => 'bpjs', 'bpjs_number' => '000990000001'], 'bpjs_number', $bpjs, '__SESI__'],
            'bpjs 14 digit' => [['payment_type' => 'bpjs', 'bpjs_number' => '00099000000112'], 'bpjs_number', $bpjs, '__SESI__'],
            'bpjs berisi huruf' => [['payment_type' => 'bpjs', 'bpjs_number' => '00099000000AB'], 'bpjs_number', $bpjs, '__SESI__'],
            'bpjs larik' => [['payment_type' => 'bpjs', 'bpjs_number' => ['0009900000011']], 'bpjs_number', $bpjs, '__SESI__'],
            'bpjs kosong tanpa nomor tersimpan' => [['payment_type' => 'bpjs'], 'bpjs_number', 'Nomor BPJS wajib diisi untuk pembayaran BPJS.', '__SESI__'],
            'bpjs untuk umum' => [['payment_type' => 'umum', 'bpjs_number' => '0009900000011'], 'bpjs_number', 'Nomor BPJS hanya boleh diisi bila jenis pembayaran adalah BPJS.', '__SESI__'],
            'bpjs untuk asuransi' => [['payment_type' => 'asuransi', 'bpjs_number' => '0009900000011', 'insurance_partner_id' => '__MITRA__'], 'bpjs_number', 'Nomor BPJS hanya boleh diisi bila jenis pembayaran adalah BPJS.', '__SESI__'],
            'asuransi tanpa mitra' => [['payment_type' => 'asuransi'], 'insurance_partner_id', 'Mitra asuransi wajib dipilih untuk pembayaran asuransi.', '__SESI__'],
            'mitra untuk umum' => [['payment_type' => 'umum', 'insurance_partner_id' => '__MITRA__'], 'insurance_partner_id', 'Mitra asuransi hanya boleh dipilih bila jenis pembayaran adalah asuransi.', '__SESI__'],
            'mitra tidak ada' => [['payment_type' => 'asuransi', 'insurance_partner_id' => '999999'], 'insurance_partner_id', $partner, '__SESI__'],
            'mitra nonaktif' => [['payment_type' => 'asuransi', 'insurance_partner_id' => '__MITRA_NONAKTIF__'], 'insurance_partner_id', $partner, '__SESI__'],
            'mitra larik' => [['payment_type' => 'asuransi', 'insurance_partner_id' => ['1']], 'insurance_partner_id', $partner, '__SESI__'],
            'polis untuk umum' => [['payment_type' => 'umum', 'policy_number' => 'POL-1'], 'policy_number', 'Nomor polis hanya boleh diisi bila jenis pembayaran adalah asuransi.', '__SESI__'],
            'polis berisi karakter terlarang' => [['payment_type' => 'asuransi', 'insurance_partner_id' => '__MITRA__', 'policy_number' => 'AB#12'], 'policy_number', 'Nomor polis hanya boleh berisi huruf, angka, garis miring, dan tanda hubung.', '__SESI__'],
            'polis 31 karakter' => [['payment_type' => 'asuransi', 'insurance_partner_id' => '__MITRA__', 'policy_number' => str_repeat('A', 31)], 'policy_number', 'Nomor polis maksimal 30 karakter.', '__SESI__'],
            'polis larik' => [['payment_type' => 'asuransi', 'insurance_partner_id' => '__MITRA__', 'policy_number' => ['AB1']], 'policy_number', 'Nomor polis tidak valid.', '__SESI__'],
            'pembayaran kosong' => [['payment_type' => null], 'payment_type', 'Jenis pembayaran wajib dipilih.', '__SESI__'],
            'pembayaran tidak dikenal' => [['payment_type' => 'tunai'], 'payment_type', $payment, '__SESI__'],
            'pembayaran larik' => [['payment_type' => ['umum']], 'payment_type', $payment, '__SESI__'],
            'keluhan 501 karakter' => [['complaint' => str_repeat('a', 501)], 'complaint', 'Keluhan maksimal 500 karakter.', '__SESI__'],
            'keluhan larik' => [['complaint' => ['a']], 'complaint', 'Keluhan tidak valid.', '__SESI__'],
            'sesi kosong' => [['practice_session_id' => null], 'practice_session_id', 'Sesi praktik wajib dipilih.', ''],
            'sesi tidak ada' => [['practice_session_id' => '999999'], 'practice_session_id', $session, '999999'],
            'sesi berupa teks' => [['practice_session_id' => 'abc'], 'practice_session_id', $session, ''],
            'sesi larik' => [['practice_session_id' => ['1']], 'practice_session_id', $session, ''],
            'save_bpjs tidak valid' => [['payment_type' => 'bpjs', 'bpjs_number' => '0009900000011', 'save_bpjs' => 'abc'], 'save_bpjs', 'Pilihan simpan nomor BPJS tidak valid.', '__SESI__'],
            'keluhan UTF-8 rusak' => [['complaint' => "a\xC3\x28"], 'complaint', 'Keluhan tidak valid.', '__SESI__'],
            'polis UTF-8 rusak' => [['payment_type' => 'asuransi', 'insurance_partner_id' => '__MITRA__', 'policy_number' => "AB \xC3\x28"], 'policy_number', 'Nomor polis hanya boleh berisi huruf, angka, garis miring, dan tanda hubung.', '__SESI__'],
            'bpjs UTF-8 rusak' => [['payment_type' => 'bpjs', 'bpjs_number' => "000990000\xC3\x28"], 'bpjs_number', $bpjs, '__SESI__'],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('invalidSubmissions')]
    public function test_an_invalid_submission_returns_to_the_wizard_with_the_field_message(array $overrides, string $field, string $message, string $sesi): void
    {
        $partner = InsurancePartner::factory()->create();
        $inactivePartner = InsurancePartner::factory()->inactive()->create();
        $session = $this->bookableSession();
        $replacements = ['__MITRA__' => (string) $partner->id, '__MITRA_NONAKTIF__' => (string) $inactivePartner->id];
        $overrides = array_map(fn (mixed $value): mixed => is_string($value) ? strtr($value, $replacements) : $value, $overrides);
        $expected = match ($sesi) {
            '__SESI__' => route('patient.appointments.create', ['sesi' => $session->id]),
            '' => route('patient.appointments.create'),
            default => route('patient.appointments.create', ['sesi' => $sesi]),
        };

        foreach (['patient.appointments.review', 'patient.appointments.store'] as $routeName) {
            $this->submit($routeName, $this->payload($overrides))
                ->assertRedirect($expected)
                ->assertSessionHasErrors([$field => $message])
                ->assertSessionMissing('_old_input.bpjs_number')
                ->assertSessionMissing('_old_input.policy_number');
        }

        $this->assertSame(0, Appointment::query()->count());
    }

    /** VAL-08: BPJS 12 digit pada `review` dan `store`: 302 ke langkah 4, input dipertahankan kecuali nomor, tidak 405. */
    #[DataProvider('submitRoutes')]
    public function test_validation_failures_redirect_to_the_wizard_and_following_the_redirect_is_a_200(string $routeName): void
    {
        $session = $this->bookableSession();
        $target = route('patient.appointments.create', ['sesi' => $session->id]);

        $this->submit($routeName, $this->payload(['payment_type' => 'bpjs', 'bpjs_number' => '000990000001', 'complaint' => 'Pusing sejak pagi.']))
            ->assertStatus(302)
            ->assertRedirect($target)
            ->assertSessionHasErrors('bpjs_number')
            ->assertSessionHas('_old_input.payment_type', 'bpjs')
            ->assertSessionHas('_old_input.complaint', 'Pusing sejak pagi.')
            ->assertSessionMissing('_old_input.bpjs_number');

        $this->get($target)
            ->assertOk()
            ->assertSee('Langkah 4 - Data pendaftaran')
            ->assertSee('Nomor BPJS harus terdiri dari 13 digit angka.')
            ->assertSee('Pusing sejak pagi.')
            ->assertSee('value="bpjs" checked', false);
    }

    #[DataProvider('submitRoutes')]
    public function test_a_full_session_found_by_the_precheck_is_a_field_error_and_the_wizard_steps_back_to_step_three(string $routeName): void
    {
        $full = $this->sessionFor($this->createDoctor(['name' => 'Bima Santoso']), '2026-10-06', ['quota' => 1, 'booked_count' => 1]);
        $target = route('patient.appointments.create', ['sesi' => $full->id]);

        $this->submit($routeName, $this->payload(['practice_session_id' => (string) $full->id]))
            ->assertRedirect($target)
            ->assertSessionHasErrors(['practice_session_id' => __('booking.errors.QUOTA_FULL')]);

        $this->get($target)->assertOk()
            ->assertSee('Langkah 3 - Pilih tanggal dan sesi')
            ->assertSee('Sesi yang dipilih tidak dapat dipesan: Penuh.');
        $this->assertSame(0, Appointment::query()->count());
    }

    #[DataProvider('submitRoutes')]
    public function test_a_session_that_does_not_exist_is_sent_back_through_the_wizard_without_a_server_error(string $routeName): void
    {
        $this->submit($routeName, $this->payload(['practice_session_id' => '999999']))
            ->assertRedirect(route('patient.appointments.create', ['sesi' => '999999']))
            ->assertSessionHasErrors(['practice_session_id' => 'Sesi praktik tidak ditemukan.']);

        $this->get(route('patient.appointments.create', ['sesi' => '999999']))
            ->assertRedirect(route('patient.appointments.create'))
            ->assertSessionHas('warning', 'Poliklinik, dokter, atau sesi yang dipilih tidak tersedia. Silakan pilih kembali.');

        $this->get(route('patient.appointments.create'))->assertOk()->assertSee('Langkah 1 - Pilih poliklinik');
    }

    /** VAL-08/SEK-06: token CSRF kedaluwarsa (419) dialihkan ke `create?sesi=`; nomor BPJS tidak ikut dikembalikan. */
    #[DataProvider('submitRoutes')]
    public function test_an_expired_csrf_token_redirects_to_the_wizard_without_the_bpjs_number(string $routeName): void
    {
        $this->app['env'] = 'local'; // ValidateCsrfToken hanya dilewati saat env `testing`
        $session = $this->bookableSession();

        $this->submit($routeName, $this->payload(['payment_type' => 'bpjs', 'bpjs_number' => self::BPJS, 'complaint' => 'Pusing sejak pagi.']))
            ->assertRedirect(route('patient.appointments.create', ['sesi' => $session->id]))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.')
            ->assertSessionHas('_old_input.complaint', 'Pusing sejak pagi.')
            ->assertSessionMissing('_old_input.bpjs_number');

        $this->assertSame(0, Appointment::query()->count());
    }

    // --- simpan (sukses) -----------------------------------------------------------------------

    public function test_store_books_the_session_and_redirects_to_the_detail_with_the_success_flash(): void
    {
        $session = $this->bookableSession();
        $user = $this->patient();

        $response = $this->submit('patient.appointments.store', $this->payload());

        $appointment = Appointment::query()->firstOrFail();
        $detail = route('patient.appointments.show', ['appointment' => $appointment->booking_code]);

        $response->assertRedirect($detail)
            ->assertSessionHas('success', 'Pendaftaran berhasil. Simpan kode booking Anda: '.$appointment->booking_code.'.');
        $this->assertMatchesRegularExpression(BookingCodeGenerator::PATTERN, $appointment->booking_code);
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame(1, $appointment->queue_number);
        $this->assertSame(BookingChannel::Online, $appointment->channel);
        $this->assertSame(PaymentType::Umum, $appointment->payment_type);
        $this->assertSame('Batuk berdahak sejak tiga hari.', $appointment->complaint);
        $this->assertSame($user->patientProfile->id, $appointment->patient_profile_id);
        $this->assertSame($session->id, $appointment->practice_session_id);
        $this->assertSame(1, $session->fresh()->booked_count);
        $this->assertSame(1, $session->fresh()->last_queue_number);

        $this->get($detail)->assertOk()->assertSee('Pendaftaran berhasil. Simpan kode booking Anda: '.$appointment->booking_code.'.');
    }

    public function test_a_typed_bpjs_number_is_stored_encrypted_and_saved_to_the_profile_when_asked(): void
    {
        $user = $this->patient();

        $this->submit('patient.appointments.store', $this->payload(['payment_type' => 'bpjs', 'bpjs_number' => '000 9900-000011', 'save_bpjs' => '1']));

        $appointment = Appointment::query()->firstOrFail();
        $row = DB::table('appointments')->where('id', $appointment->id)->first();

        $this->assertSame(PaymentType::Bpjs, $appointment->payment_type);
        $this->assertSame(self::BPJS, $appointment->payment_reference);
        $this->assertSame('0011', $row->payment_reference_last4);
        $this->assertNotSame(self::BPJS, $row->payment_reference);
        $this->assertStringNotContainsString(self::BPJS, (string) $row->payment_reference);
        $this->assertSame(self::BPJS, $user->patientProfile->fresh()->bpjs_number);
        $this->assertSame('0011', $user->patientProfile->fresh()->bpjs_number_last4);
    }

    public function test_the_bpjs_number_is_not_saved_to_the_profile_unless_asked(): void
    {
        $user = $this->patient();

        $this->submit('patient.appointments.store', $this->payload(['payment_type' => 'bpjs', 'bpjs_number' => self::BPJS]));

        $this->assertSame(self::BPJS, Appointment::query()->firstOrFail()->payment_reference);
        $this->assertNull($user->patientProfile->fresh()->bpjs_number);
    }

    public function test_the_stored_bpjs_number_is_used_when_the_field_is_left_empty(): void
    {
        $user = $this->patient(['bpjs_number' => self::BPJS]);

        $this->submit('patient.appointments.store', $this->payload(['payment_type' => 'bpjs', 'save_bpjs' => '1']));

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame(self::BPJS, $appointment->payment_reference);
        $this->assertSame('0011', $appointment->payment_reference_last4);
        $this->assertSame(self::BPJS, $user->patientProfile->fresh()->bpjs_number);
    }

    public function test_an_insurance_booking_stores_the_partner_and_the_normalised_policy_number(): void
    {
        $partner = InsurancePartner::factory()->create();

        $this->submit('patient.appointments.store', $this->payload(['payment_type' => 'asuransi', 'insurance_partner_id' => (string) $partner->id, 'policy_number' => 'AB 123-45']));

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame(PaymentType::Asuransi, $appointment->payment_type);
        $this->assertSame($partner->id, $appointment->insurance_partner_id);
        $this->assertSame('AB123-45', $appointment->payment_reference);
    }

    // --- field tersembunyi dipalsukan (Review Focus 4) ------------------------------------------

    public function test_the_hidden_fields_of_the_review_page_book_exactly_the_reviewed_data(): void
    {
        $partner = InsurancePartner::factory()->create();
        $review = $this->submit('patient.appointments.review', $this->payload([
            'payment_type' => 'asuransi',
            'insurance_partner_id' => (string) $partner->id,
            'policy_number' => 'AB 123-45',
        ]))->assertOk();

        $this->submit('patient.appointments.store', $this->hiddenFields($review->getContent()));

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame(PaymentType::Asuransi, $appointment->payment_type);
        $this->assertSame($partner->id, $appointment->insurance_partner_id);
        $this->assertSame('AB123-45', $appointment->payment_reference);
        $this->assertSame('Batuk berdahak sejak tiga hari.', $appointment->complaint);
    }

    public function test_tampered_system_fields_are_never_read_from_the_input(): void
    {
        $user = $this->patient();
        $other = $this->createPatient(['email' => 'lain@contoh.test']);

        $this->submit('patient.appointments.store', $this->payload([
            'patient_profile_id' => (string) $other->patientProfile->id,
            'user_id' => (string) $other->id,
            'status' => 'confirmed',
            'booking_code' => 'UPH-AAAA-AAAA',
            'queue_number' => '99',
            'channel' => 'loket',
            'created_by_user_id' => (string) $other->id,
            'cancelled_by_type' => 'patient',
            'payment_reference' => '0001234567890',
            'doctor_id' => '1',
            'clinic_id' => '1',
            'session_date' => '2030-01-01',
        ]));

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame($user->patientProfile->id, $appointment->patient_profile_id);
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertNotSame('UPH-AAAA-AAAA', $appointment->booking_code);
        $this->assertSame(1, $appointment->queue_number);
        $this->assertSame(BookingChannel::Online, $appointment->channel);
        $this->assertNull($appointment->created_by_user_id, 'Pendaftaran online tidak mencatat petugas pembuat.');
        $this->assertNull($appointment->cancelled_by_type);
        $this->assertNull($appointment->payment_reference, 'Pembayaran umum tidak menyimpan referensi.');
        $this->assertSame($this->bookableSession()->doctor_id, $appointment->doctor_id);
        $this->assertSame($this->bookableSession()->session_date->toDateString(), $appointment->session_date->toDateString());
        $this->assertSame(0, Appointment::query()->where('patient_profile_id', $other->patientProfile->id)->count());
    }

    public function test_a_tampered_session_id_books_that_session_only_if_the_rules_allow_it(): void
    {
        $this->patient();
        $other = $this->sessionFor($this->createDoctor(['name' => 'Bima Santoso']), '2026-10-07');
        $closed = $this->sessionFor($this->createDoctor(['name' => 'Citra Lestari']), '2026-10-07', ['status' => SessionStatus::Closed]);

        $this->submit('patient.appointments.store', $this->payload(['practice_session_id' => (string) $closed->id]))
            ->assertRedirect(route('patient.appointments.create', ['sesi' => $closed->id]))
            ->assertSessionHasErrors(['practice_session_id' => __('booking.errors.SESSION_UNAVAILABLE')]);
        $this->assertSame(0, Appointment::query()->count());

        $this->submit('patient.appointments.store', $this->payload(['practice_session_id' => (string) $other->id]));
        $this->assertSame($other->id, Appointment::query()->firstOrFail()->practice_session_id, 'Data divalidasi ulang; sesi lain yang sah dipesan apa adanya.');
    }

    // --- kirim ganda (F6-R9, Review Focus 1) ----------------------------------------------------

    public function test_a_held_submit_lock_answers_with_the_processing_message_and_books_nothing(): void
    {
        $user = $this->patient();
        $session = $this->bookableSession();
        $lock = Cache::lock('booking-submit:'.$user->id, 10);
        $this->assertTrue($lock->get());

        $this->submit('patient.appointments.store', $this->payload(['payment_type' => 'bpjs', 'bpjs_number' => self::BPJS]))
            ->assertRedirect(route('patient.appointments.create', ['sesi' => $session->id]))
            ->assertSessionHas('error', 'Permintaan Anda sedang diproses. Mohon tunggu sebentar dan jangan menekan tombol berulang kali.')
            ->assertSessionHas('_old_input.payment_type', 'bpjs')
            ->assertSessionMissing('_old_input.bpjs_number');

        $this->assertSame(0, Appointment::query()->count());
        $this->assertSame(0, $session->fresh()->booked_count);
        $this->assertSame(0, $session->fresh()->last_queue_number);

        $lock->release();

        $this->submit('patient.appointments.store', $this->payload());
        $this->assertSame(1, Appointment::query()->count(), 'Sesudah lock dilepas, pengiriman berikutnya berhasil.');
    }

    public function test_the_lock_is_per_patient_and_released_after_a_successful_booking(): void
    {
        $user = $this->patient();
        $other = $this->createPatient(['email' => 'lain@contoh.test']);
        $this->assertTrue(Cache::lock('booking-submit:'.$user->id, 10)->get());

        $this->submit('patient.appointments.store', $this->payload(), $other)->assertSessionHas('success');
        $this->assertSame(1, Appointment::query()->count(), 'Pasien lain tidak ditahan oleh lock pasien ini.');

        Cache::lock('booking-submit:'.$user->id)->forceRelease();
        $this->submit('patient.appointments.store', $this->payload(), $user)->assertSessionHas('success');

        $lock = Cache::lock('booking-submit:'.$user->id, 10);
        $this->assertTrue($lock->get(), 'Lock dilepas di finally setelah booking selesai.');
        $lock->release();
    }

    public function test_the_lock_is_released_even_when_booking_fails(): void
    {
        $user = $this->patient();
        $data = $this->payload();
        $this->beforeBooking(function (): void {
            throw BookingException::make(BookingException::SYSTEM_BUSY);
        });

        $this->submit('patient.appointments.store', $data)->assertSessionHas('error');

        $lock = Cache::lock('booking-submit:'.$user->id, 10);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_a_second_submission_of_the_same_payload_is_a_duplicate_with_only_one_appointment(): void
    {
        $session = $this->bookableSession();

        $this->submit('patient.appointments.store', $this->payload())->assertSessionHas('success');
        $this->submit('patient.appointments.store', $this->payload())
            ->assertRedirect(route('patient.appointments.create', ['sesi' => $session->id]))
            ->assertSessionHasErrors(['practice_session_id' => 'Anda sudah memiliki pendaftaran aktif pada sesi dokter ini.']);

        $this->assertSame(1, Appointment::query()->count());
        $this->assertSame(1, $session->fresh()->booked_count);
    }

    // --- throttle (SEK-05, F6-R37) --------------------------------------------------------------

    public function test_the_eleventh_store_in_a_minute_is_throttled_with_a_redirect_and_retry_after(): void
    {
        $user = $this->patient();
        $session = $this->bookableSession();
        $data = ['practice_session_id' => (string) $session->id];

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->submit('patient.appointments.store', $data, $user)->assertStatus(302)->assertSessionHasErrors('payment_type');
        }

        $this->submit('patient.appointments.store', $data, $user)
            ->assertStatus(302)
            ->assertRedirect(route('patient.appointments.create', ['sesi' => $session->id]))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn (string $message): bool => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1);

        $this->assertSame(0, Appointment::query()->count());
    }

    // --- pengalihan per kode (F6-R10, BKG-20) -----------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function bookingFailures(): array
    {
        return [
            'SESSION_UNAVAILABLE' => [BookingException::SESSION_UNAVAILABLE, 'langkah3'],
            'OUTSIDE_WINDOW' => [BookingException::OUTSIDE_WINDOW, 'langkah3'],
            'CUTOFF_PASSED' => [BookingException::CUTOFF_PASSED, 'langkah3'],
            'QUOTA_FULL' => [BookingException::QUOTA_FULL, 'langkah3'],
            'OVERLAPPING_BOOKING' => [BookingException::OVERLAPPING_BOOKING, 'langkah3'],
            'INVALID_PAYMENT' => [BookingException::INVALID_PAYMENT, 'langkah4'],
            'SYSTEM_BUSY' => [BookingException::SYSTEM_BUSY, 'langkah4'],
            'DUPLICATE_BOOKING' => [BookingException::DUPLICATE_BOOKING, 'detail'],
            'ACTIVE_LIMIT_REACHED' => [BookingException::ACTIVE_LIMIT_REACHED, 'riwayat'],
        ];
    }

    #[DataProvider('bookingFailures')]
    public function test_a_booking_exception_from_book_is_redirected_per_code_with_its_message_as_a_flash(string $code, string $kind): void
    {
        $user = $this->patient();
        $session = $this->bookableSession();
        $existing = $this->bookAppointment(
            $this->sessionFor($this->createDoctor(), '2026-10-12'),
            AppointmentStatus::Pending,
            ['patient_profile_id' => $user->patientProfile->id],
        );
        $exception = BookingException::make($code, [], $code === BookingException::DUPLICATE_BOOKING ? ['appointment_code' => $existing->booking_code] : []);
        $expected = match ($kind) {
            'langkah3' => $this->wizardStepThree($session),
            'langkah4' => route('patient.appointments.create', ['sesi' => $session->id]),
            'detail' => route('patient.appointments.show', ['appointment' => $existing->booking_code]),
            'riwayat' => Route::has('patient.appointments.index') ? route('patient.appointments.index') : route('patient.dashboard'),
        };
        $before = Appointment::query()->count();

        $this->beforeBooking(function () use ($exception): void {
            throw $exception;
        });

        $this->submit('patient.appointments.store', $this->payload(['payment_type' => 'bpjs', 'bpjs_number' => self::BPJS, 'complaint' => 'Pusing sejak pagi.']))
            ->assertStatus(302)
            ->assertRedirect($expected)
            ->assertSessionHas('error', $exception->getMessage())
            ->assertSessionHas('_old_input.payment_type', 'bpjs')
            ->assertSessionHas('_old_input.complaint', 'Pusing sejak pagi.')
            ->assertSessionMissing('_old_input.bpjs_number');

        $this->assertSame($before, Appointment::query()->count());
    }

    public function test_a_quota_race_between_validation_and_booking_returns_to_step_three_with_the_real_message(): void
    {
        $session = $this->bookableSession();
        $this->patient();

        $this->beforeBooking(function () use ($session): void {
            DB::table('practice_sessions')->where('id', $session->id)->update(['booked_count' => 20, 'last_queue_number' => 20]);
        });

        $this->submit('patient.appointments.store', $this->payload())
            ->assertRedirect($this->wizardStepThree($session))
            ->assertSessionHas('error', __('booking.errors.QUOTA_FULL'));

        $this->assertSame(0, Appointment::query()->count());
        $this->assertSame(20, $session->fresh()->booked_count, 'Hanya perubahan simulasi; book() tidak menulis apa pun.');
    }

    public function test_a_duplicate_created_between_validation_and_booking_redirects_to_that_appointment(): void
    {
        $user = $this->patient();
        $session = $this->bookableSession();

        $this->beforeBooking(function () use ($session, $user): void {
            $this->bookAppointment($session, AppointmentStatus::Pending, ['patient_profile_id' => $user->patientProfile->id]);
        });

        $response = $this->submit('patient.appointments.store', $this->payload());
        $existing = Appointment::query()->firstOrFail();

        $response->assertRedirect(route('patient.appointments.show', ['appointment' => $existing->booking_code]))
            ->assertSessionHas('error', 'Anda sudah memiliki pendaftaran aktif pada sesi dokter ini.');
        $this->assertSame(1, Appointment::query()->count());
    }
}
