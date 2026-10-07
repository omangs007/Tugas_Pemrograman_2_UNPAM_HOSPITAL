<?php

namespace Tests\Feature\Public;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `POST /kontak` `contact.store` (06 §17, F4B-R6 s.d. F4B-R8, F4B-R34, F4B-R35, Review Focus 2): validasi V-37 dengan
 * pesan persis, penyimpanan tanpa IP mentah, throttle kiriman keempat, honeypot, persetujuan, normalisasi telepon,
 * nilai larik tanpa 500, dan CSRF. Waktu dibekukan pada Senin 5 Oktober 2026 pukul 10.00 WIB.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private const SUCCESS = 'Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00:00');
        // Kunci ThrottleRequests = md5(nama limiter . kunci Limit) (lihat AdminLoginController).
        RateLimiter::clear(md5('contact'.'127.0.0.1'));
        RateLimiter::clear(md5('contact'.'203.0.113.9'));
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function valid(array $override = []): array
    {
        return array_merge([
            'name' => 'Andi Pratama',
            'email' => 'andi@contoh.test',
            'phone' => '081234567890',
            'subject' => 'Pertanyaan jadwal dokter',
            'message' => 'Mohon info jadwal praktik dokter anak minggu depan.',
            'consent' => '1',
        ], $override);
    }

    /** @param  array<string, mixed>  $data */
    private function submit(array $data): TestResponse
    {
        return $this->from(route('contact'))->post(route('contact.store'), $data);
    }

    // --- sukses dan penyimpanan -----------------------------------------------------------------------------------

    public function test_a_valid_submission_is_saved_and_redirects_with_the_exact_flash(): void
    {
        $this->submit($this->valid())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', self::SUCCESS)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ContactMessage::query()->count());

        $message = ContactMessage::query()->firstOrFail();

        $this->assertSame('Andi Pratama', $message->name);
        $this->assertSame('andi@contoh.test', $message->email);
        $this->assertSame('081234567890', $message->phone);
        $this->assertSame('Pertanyaan jadwal dokter', $message->subject);
        $this->assertSame('Mohon info jadwal praktik dokter anak minggu depan.', $message->message);
        $this->assertSame(ContactMessageStatus::New, $message->status);
        $this->assertTrue($message->consent_at->equalTo(now()));
        $this->assertNull($message->getAttribute('handled_by'));
        $this->assertNull($message->getAttribute('handled_at'));
    }

    public function test_the_flash_is_shown_after_following_the_redirect(): void
    {
        $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid())
            ->assertOk()
            ->assertSee('data-flash="success"', false)
            ->assertSee(self::SUCCESS);
    }

    public function test_text_fields_are_trimmed_and_the_phone_is_optional(): void
    {
        $this->submit($this->valid(['name' => '  Siti Nur’aini  ', 'phone' => '', 'subject' => '  Subjek dengan spasi  ']))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success');

        $message = ContactMessage::query()->firstOrFail();

        $this->assertSame('Siti Nur’aini', $message->name);
        $this->assertSame('Subjek dengan spasi', $message->subject);
        $this->assertNull($message->phone);
    }

    // --- ip_hash (SEK-14) -------------------------------------------------------------------------------------------

    public function test_only_a_sha256_hash_of_the_ip_and_app_key_is_stored(): void
    {
        $this->submit($this->valid())->assertSessionHas('success');

        $row = DB::table('contact_messages')->first();

        $this->assertSame(hash('sha256', '127.0.0.1'.config('app.key')), $row->ip_hash);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $row->ip_hash);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($row, JSON_THROW_ON_ERROR));
    }

    public function test_a_different_ip_produces_a_different_hash(): void
    {
        $this->submit($this->valid());
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->submit($this->valid(['subject' => 'Kiriman dari alamat lain']));

        $hashes = DB::table('contact_messages')->orderBy('id')->pluck('ip_hash')->all();

        $this->assertCount(2, $hashes);
        $this->assertNotSame($hashes[0], $hashes[1]);
        $this->assertSame(hash('sha256', '203.0.113.9'.config('app.key')), $hashes[1]);
    }

    // --- validasi V-37 (VAL-04) -------------------------------------------------------------------------------------

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: string}> */
    public static function invalidInput(): array
    {
        return [
            'nama kosong' => ['name', ['name' => ''], 'Nama wajib diisi.'],
            'nama terlalu pendek' => ['name', ['name' => 'Ab'], 'Nama minimal 3 karakter.'],
            'nama terlalu panjang' => ['name', ['name' => str_repeat('a', 101)], 'Nama maksimal 100 karakter.'],
            'nama berangka' => ['name', ['name' => 'Andi 2'], 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.'],
            'nama bertag' => ['name', ['name' => '<script>alert(1)</script>'], 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.'],
            'nama larik' => ['name', ['name' => ['x']], 'Nama harus berupa teks.'],
            'email kosong' => ['email', ['email' => ''], 'Email wajib diisi.'],
            'email tidak valid' => ['email', ['email' => 'bukan-email'], 'Format email tidak valid.'],
            'email tanpa domain' => ['email', ['email' => 'andi@'], 'Format email tidak valid.'],
            'email terlalu panjang' => ['email', ['email' => str_repeat('a', 250).'@x.test'], 'Email maksimal 255 karakter.'],
            'email larik' => ['email', ['email' => ['a@b.test']], 'Format email tidak valid.'],
            'telepon rumah' => ['phone', ['phone' => '021555'], 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'telepon huruf' => ['phone', ['phone' => '0812abc4567'], 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'telepon terlalu panjang' => ['phone', ['phone' => '08'.'1234567890123'], 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'telepon larik' => ['phone', ['phone' => ['081234567890']], 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'subjek kosong' => ['subject', ['subject' => ''], 'Subjek wajib diisi.'],
            'subjek terlalu pendek' => ['subject', ['subject' => 'abcd'], 'Subjek minimal 5 karakter.'],
            'subjek terlalu panjang' => ['subject', ['subject' => str_repeat('a', 151)], 'Subjek maksimal 150 karakter.'],
            'subjek larik' => ['subject', ['subject' => ['x']], 'Subjek harus berupa teks.'],
            'pesan kosong' => ['message', ['message' => ''], 'Pesan wajib diisi.'],
            'pesan terlalu pendek' => ['message', ['message' => 'pendek'], 'Pesan minimal 10 karakter.'],
            'pesan terlalu panjang' => ['message', ['message' => str_repeat('a', 2001)], 'Pesan maksimal 2000 karakter.'],
            'pesan larik' => ['message', ['message' => ['x']], 'Pesan harus berupa teks.'],
            'persetujuan tidak dikirim' => ['consent', ['consent' => null], 'Anda harus menyetujui pengolahan data untuk mengirim pesan.'],
            'persetujuan nol' => ['consent', ['consent' => '0'], 'Anda harus menyetujui pengolahan data untuk mengirim pesan.'],
            'persetujuan larik' => ['consent', ['consent' => ['1']], 'Anda harus menyetujui pengolahan data untuk mengirim pesan.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected_with_the_exact_message_and_nothing_is_saved(string $field, array $override, string $message): void
    {
        $this->submit($this->valid($override))
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors([$field => $message])
            ->assertSessionMissing('success');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_boundary_lengths_are_accepted(): void
    {
        $this->submit($this->valid([
            'name' => 'Abc',
            'subject' => 'Lima!',
            'message' => 'Sepuluh ok',
        ]))->assertSessionHas('success');

        $this->submit($this->valid([
            'name' => str_repeat('a', 100),
            'subject' => str_repeat('s', 150),
            'message' => str_repeat('m', 2000),
        ]))->assertSessionHas('success');

        $this->assertSame(2, ContactMessage::query()->count());
    }

    public function test_every_field_as_an_array_is_rejected_without_a_server_error(): void
    {
        $response = $this->submit([
            'name' => ['x'],
            'email' => ['x'],
            'phone' => ['x'],
            'subject' => ['x'],
            'message' => ['x'],
            'consent' => ['1'],
            'website' => ['x'],
        ]);

        $response->assertRedirect(route('contact'))
            ->assertSessionHasErrors(['name', 'email', 'phone', 'subject', 'message', 'consent']);
        $this->assertSame(0, ContactMessage::query()->count());

        // Menampilkan ulang halaman dengan nilai lama berupa larik tidak boleh menjadi 500.
        $this->followingRedirects()->from(route('contact'))->post(route('contact.store'), [
            'name' => ['x'], 'email' => ['x'], 'phone' => ['x'], 'subject' => ['x'], 'message' => ['x'], 'consent' => ['1'],
        ])->assertOk()->assertSee('Nama harus berupa teks.');
    }

    public function test_the_failed_form_keeps_old_input_and_shows_the_error_summary(): void
    {
        $response = $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid(['name' => 'Ab', 'subject' => 'Subjek dipertahankan']))
            ->assertOk();

        $response->assertSee('data-errors-summary', false)
            ->assertSee('Periksa kembali isian Anda')
            ->assertSee('Nama minimal 3 karakter.')
            ->assertSee('id="name-error" class="invalid-feedback d-block"', false)
            ->assertSee('value="Ab"', false)
            ->assertSee('value="Subjek dipertahankan"', false)
            ->assertSee('Mohon info jadwal praktik dokter anak minggu depan.')
            ->assertSeeInOrder(['id="consent"', 'name="consent"', 'value="1"', 'checked'], false);
        $this->assertSame(1, preg_match('/<input type="checkbox" id="consent" name="consent" value="1"\s+checked/', $response->getContent()));
    }

    public function test_the_honeypot_input_is_never_repopulated(): void
    {
        $response = $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid(['name' => 'Ab', 'website' => 'http://spam.test']))
            ->assertOk();

        $response->assertDontSee('http://spam.test', false);
    }

    // --- persetujuan (F4B-R8) -----------------------------------------------------------------------------------

    public function test_the_consent_message_is_the_exact_text(): void
    {
        $this->submit($this->valid(['consent' => null]))
            ->assertSessionHasErrors(['consent' => 'Anda harus menyetujui pengolahan data untuk mengirim pesan.']);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    // --- telepon dinormalisasi (F4B-R34) --------------------------------------------------------------------------

    /** @return array<string, array{0: string, 1: string}> */
    public static function phones(): array
    {
        return [
            'dengan tanda hubung' => ['0812-3456-7890', '081234567890'],
            'format plus enam dua' => ['+62 812 3456 7890', '+6281234567890'],
            'dengan kurung dan titik' => ['(0812) 3456.7890', '081234567890'],
            'format enam dua tanpa plus' => ['6281234567890', '6281234567890'],
        ];
    }

    #[DataProvider('phones')]
    public function test_the_phone_is_stored_normalized(string $input, string $stored): void
    {
        $this->submit($this->valid(['phone' => $input]))->assertSessionHas('success');

        $this->assertSame($stored, ContactMessage::query()->firstOrFail()->phone);
        $this->assertLessThanOrEqual(16, strlen($stored));
    }

    // --- XSS: teks tersimpan apa adanya, ter-escape saat dirender (SEK-15) ---------------------------------------------

    public function test_script_text_in_subject_and_message_is_stored_as_is(): void
    {
        $this->submit($this->valid([
            'subject' => '<script>alert(1)</script> Subjek',
            'message' => "<script>alert('x')</script> isi pesan panjang",
        ]))->assertSessionHas('success');

        $message = ContactMessage::query()->firstOrFail();

        $this->assertSame('<script>alert(1)</script> Subjek', $message->subject);
        $this->assertSame("<script>alert('x')</script> isi pesan panjang", $message->message);
    }

    public function test_script_text_shown_back_as_old_input_is_escaped(): void
    {
        $response = $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid([
                'consent' => null,
                'subject' => '<script>alert(1)</script> Subjek',
                'message' => '<script>alert(2)</script> isi pesan panjang',
            ]))
            ->assertOk();

        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Subjek', false)
            ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt; isi pesan panjang', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('<script>alert(2)', false);
    }

    // --- honeypot (F4B-R7) ------------------------------------------------------------------------------------------------

    public function test_a_filled_honeypot_shows_a_fake_success_and_saves_nothing(): void
    {
        $this->submit($this->valid(['website' => 'http://spam.test']))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', self::SUCCESS)
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_an_array_honeypot_counts_as_filled_without_a_server_error(): void
    {
        $this->submit($this->valid(['website' => ['x']]))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', self::SUCCESS)
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_an_empty_honeypot_does_not_prevent_saving(): void
    {
        $this->submit($this->valid(['website' => '']))->assertSessionHas('success', self::SUCCESS);

        $this->assertSame(1, ContactMessage::query()->count());
    }

    // --- throttle (F4B-R6, SEK-14) -------------------------------------------------------------------------------------------

    public function test_the_fourth_submission_in_ten_minutes_is_throttled_without_saving(): void
    {
        foreach ([1, 2, 3] as $number) {
            $this->submit($this->valid(['subject' => "Subjek kiriman {$number}"]))
                ->assertRedirect(route('contact'))
                ->assertSessionHas('success', self::SUCCESS);
        }

        $this->assertSame(3, ContactMessage::query()->count());

        $fourth = $this->submit($this->valid(['subject' => 'Subjek kiriman keempat']));

        $fourth->assertRedirect(route('contact'))
            ->assertStatus(302)
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1)
            ->assertSessionMissing('success')
            ->assertSessionHasInput('subject', 'Subjek kiriman keempat');

        $this->assertSame(3, ContactMessage::query()->count());
        $this->assertSame(0, ContactMessage::query()->where('subject', 'Subjek kiriman keempat')->count());
        $this->assertGreaterThan(0, (int) $fourth->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(600, (int) $fourth->headers->get('Retry-After'));
    }

    public function test_the_throttled_page_shows_the_error_flash_after_following_the_redirect(): void
    {
        foreach ([1, 2, 3] as $number) {
            $this->submit($this->valid(['subject' => "Subjek kiriman {$number}"]));
        }

        $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid(['subject' => 'Subjek keempat tertahan']))
            ->assertOk()
            ->assertSee('data-flash="error"', false)
            ->assertSee('Terlalu banyak percobaan. Silakan coba lagi dalam')
            ->assertSee('value="Subjek keempat tertahan"', false);
    }

    public function test_invalid_submissions_also_count_toward_the_limit(): void
    {
        foreach ([1, 2, 3] as $ignored) {
            $this->submit($this->valid(['name' => 'Ab']))->assertSessionHasErrors('name');
        }

        $this->submit($this->valid())
            ->assertHeader('Retry-After')
            ->assertSessionHas('error');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_another_ip_is_not_blocked(): void
    {
        foreach ([1, 2, 3] as $number) {
            $this->submit($this->valid(['subject' => "Subjek kiriman {$number}"]));
        }

        $this->submit($this->valid())->assertHeader('Retry-After');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->submit($this->valid(['subject' => 'Kiriman dari alamat lain']))
            ->assertSessionHas('success', self::SUCCESS);

        $this->assertSame(4, ContactMessage::query()->count());
    }

    public function test_the_limit_is_released_after_ten_minutes(): void
    {
        foreach ([1, 2, 3] as $number) {
            $this->submit($this->valid(['subject' => "Subjek kiriman {$number}"]));
        }

        $this->submit($this->valid())->assertHeader('Retry-After');

        $this->travel(11)->minutes();

        $this->submit($this->valid(['subject' => 'Kiriman setelah jeda']))->assertSessionHas('success', self::SUCCESS);
        $this->assertSame(4, ContactMessage::query()->count());
    }

    // --- CSRF (SEK-06) -----------------------------------------------------------------------------------------------------------

    public function test_a_missing_csrf_token_is_rejected_with_a_redirect_and_nothing_is_saved(): void
    {
        $this->app['env'] = 'local'; // ValidateCsrfToken hanya dilewati saat env `testing`

        $this->submit($this->valid())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_the_form_is_accepted_with_a_valid_csrf_token_when_the_check_is_active(): void
    {
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'token-uji'])
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid() + ['_token' => 'token-uji'])
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', self::SUCCESS);

        $this->assertSame(1, ContactMessage::query()->count());
    }

    // --- metode lain -------------------------------------------------------------------------------------------------------------------

    public function test_other_http_methods_on_the_contact_url_are_not_allowed(): void
    {
        $this->put('/kontak', $this->valid())->assertStatus(405);
        $this->patch('/kontak', $this->valid())->assertStatus(405);
        $this->delete('/kontak')->assertStatus(405);
        $this->assertSame(0, ContactMessage::query()->count());
    }
}
