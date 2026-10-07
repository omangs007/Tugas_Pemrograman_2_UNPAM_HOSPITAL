<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Enums\ContactMessageStatus;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\User;
use App\Policies\ContactMessagePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin kotak pesan kontak (T6, F4B-R23, F4B-R24, F4B-R39): akses (Petugas dan Super Admin, bukan Admin Konten),
 * daftar dan filter, escape output (SEK-15), status pesan, serta audit tanpa data pribadi (Review Focus 5).
 */
class AdminContactMessagesTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 6 Oktober 2026 pukul 10.30 WIB.
        $this->travelTo(Carbon::parse('2026-10-06 10:30:00'));
    }

    private function statusUrl(ContactMessage $message): string
    {
        return route('admin.messages.status', $message);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function message(array $overrides = []): ContactMessage
    {
        return $this->createContactMessage(array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'subject' => 'Jadwal dokter anak',
            'message' => 'Apakah dokter anak praktik pada hari Sabtu?',
            'status' => ContactMessageStatus::New,
        ], $overrides));
    }

    // --- Akses (AKS-04, AKS-05, F4B-R39) --------------------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function staffRoles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'petugas pendaftaran' => ['registration-officer', 200],
            'admin konten' => ['content-admin', 403],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_index_and_show_access_per_staff_role(string $role, int $status): void
    {
        $message = $this->message();
        $this->actingAs($this->createStaff($role));

        $this->get(route('admin.messages.index'))->assertStatus($status);
        $this->get(route('admin.messages.show', $message))->assertStatus($status);
    }

    public function test_content_admin_is_forbidden_on_every_message_route_including_the_status_patch(): void
    {
        $message = $this->message();
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.messages.index'))->assertForbidden();
        $this->get(route('admin.messages.show', $message))->assertForbidden();
        $this->patch($this->statusUrl($message), ['status' => 'archived'])->assertForbidden();

        $message->refresh();
        $this->assertSame(ContactMessageStatus::New, $message->status);
        $this->assertNull($message->handled_by);
        $this->assertSame(0, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $message = $this->message();

        $this->actingAs(User::factory()->patient()->create());
        $this->get(route('admin.messages.index'))->assertForbidden();
        $this->get(route('admin.messages.show', $message))->assertForbidden();
        $this->patch($this->statusUrl($message), ['status' => 'read'])->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.messages.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.messages.show', $message))->assertRedirect(route('admin.login'));
        $this->patch($this->statusUrl($message), ['status' => 'read'])->assertRedirect(route('admin.login'));

        $this->assertSame(ContactMessageStatus::New, $message->fresh()->status);
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $message = $this->message();

        $this->assertSame(url('/admin/pesan'), route('admin.messages.index'));
        $this->assertSame(url('/admin/pesan/'.$message->id), route('admin.messages.show', $message));
        $this->assertSame(url('/admin/pesan/'.$message->id.'/status'), route('admin.messages.status', $message));
    }

    public function test_there_is_no_destroy_route_and_deleting_is_not_possible(): void
    {
        $message = $this->message();

        $this->assertFalse(Route::has('admin.messages.destroy'));

        $this->actingAs($this->createStaff('super-admin'))
            ->delete('/admin/pesan/'.$message->id)
            ->assertStatus(405);

        $this->assertDatabaseHas('contact_messages', ['id' => $message->id]);

        $policy = new ContactMessagePolicy;
        $this->assertFalse($policy->delete($this->createStaff('registration-officer'), $message));
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get('/admin/pesan/abc')->assertNotFound();
        $this->patch('/admin/pesan/abc/status', ['status' => 'read'])->assertNotFound();
        $this->get('/admin/pesan/999999')->assertNotFound();
    }

    public function test_the_sidebar_link_appears_for_the_officer_but_not_for_content_admin(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.messages.index').'"', false)
            ->assertSee('Pesan Kontak');

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('admin.messages.index').'"', false);
    }

    // --- Daftar ---------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_newest_first_with_status_badges_and_bold_new_rows(): void
    {
        $oldestNew = $this->message(['name' => 'Pengirim Satu', 'subject' => 'Subjek Satu', 'created_at' => now()->subDays(5)]);
        $this->message(['name' => 'Pengirim Dua', 'subject' => 'Subjek Dua', 'status' => ContactMessageStatus::Read, 'created_at' => now()->subDays(4)]);
        $this->message(['name' => 'Pengirim Tiga', 'subject' => 'Subjek Tiga', 'status' => ContactMessageStatus::Replied, 'created_at' => now()->subDays(3)]);
        $this->message(['name' => 'Pengirim Empat', 'subject' => 'Subjek Empat', 'status' => ContactMessageStatus::Archived, 'created_at' => now()->subDays(2)]);
        $newest = $this->message(['name' => 'Pengirim Lima', 'subject' => 'Subjek Lima', 'email' => 'lima@contoh.test', 'created_at' => now()->subDay()]);

        $html = $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSeeInOrder(['Subjek Lima', 'Subjek Empat', 'Subjek Tiga', 'Subjek Dua', 'Subjek Satu'])
            ->assertSee('lima@contoh.test')
            ->assertSee('<i class="bi bi-envelope" aria-hidden="true"></i> Baru', false)
            ->assertSee('<i class="bi bi-envelope-open" aria-hidden="true"></i> Dibaca', false)
            ->assertSee('<i class="bi bi-reply" aria-hidden="true"></i> Dibalas', false)
            ->assertSee('<i class="bi bi-archive" aria-hidden="true"></i> Diarsipkan', false)
            ->assertSee('1 Okt 2026, 10.30')
            ->assertSee('5 Okt 2026, 10.30')
            ->assertSee('href="'.route('admin.messages.show', $oldestNew).'"', false)
            ->assertSee('href="'.route('admin.messages.show', $newest).'"', false)
            ->assertSee('Cari nama, email, atau subjek…')
            ->assertDontSee('name="_method" value="DELETE"', false)
            ->assertDontSee('data-confirm-action="'.url('/admin/pesan'), false)
            ->getContent();

        $this->assertSame(2, substr_count($html, '<tr class="fw-bold">'));
    }

    public function test_search_and_status_filters(): void
    {
        $this->message(['name' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'subject' => 'Jadwal dokter anak']);
        $this->message(['name' => 'Citra Lestari', 'email' => 'citra@lain.test', 'subject' => 'Biaya rawat inap', 'status' => ContactMessageStatus::Read]);
        $this->message(['name' => 'Dewi Anggraini', 'email' => 'dewi@contoh.test', 'subject' => 'Keluhan pelayanan', 'status' => ContactMessageStatus::Replied]);
        $this->message(['name' => 'Eko Prasetyo', 'email' => 'eko@contoh.test', 'subject' => 'Pesan lama', 'status' => ContactMessageStatus::Archived]);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.index', ['q' => 'santoso']))
            ->assertOk()->assertSee('Jadwal dokter anak')->assertDontSee('Biaya rawat inap');

        $this->get(route('admin.messages.index', ['q' => 'citra@lain']))
            ->assertOk()->assertSee('Biaya rawat inap')->assertDontSee('Jadwal dokter anak');

        $this->get(route('admin.messages.index', ['q' => 'rawat inap']))
            ->assertOk()->assertSee('Biaya rawat inap')->assertDontSee('Keluhan pelayanan');

        foreach ([
            'baru' => 'Jadwal dokter anak',
            'dibaca' => 'Biaya rawat inap',
            'dibalas' => 'Keluhan pelayanan',
            'diarsipkan' => 'Pesan lama',
        ] as $status => $subject) {
            $response = $this->get(route('admin.messages.index', ['status' => $status]))->assertOk()->assertSee($subject);

            foreach (['Jadwal dokter anak', 'Biaya rawat inap', 'Keluhan pelayanan', 'Pesan lama'] as $other) {
                if ($other !== $subject) {
                    $response->assertDontSee($other);
                }
            }
        }
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $this->message(['subject' => 'Diskon 100% promo']);
        $this->message(['subject' => 'Diskon 1000 promo']);
        $this->message(['subject' => 'Kode_A tiket']);
        $this->message(['subject' => 'KodeXA tiket']);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.index', ['q' => '100%']))
            ->assertOk()->assertSee('Diskon 100% promo')->assertDontSee('Diskon 1000 promo');

        $this->get(route('admin.messages.index', ['q' => 'Kode_A']))
            ->assertOk()->assertSee('Kode_A tiket')->assertDontSee('KodeXA tiket');
    }

    public function test_invalid_filters_are_ignored_and_array_params_do_not_cause_a_server_error(): void
    {
        $this->message(['subject' => 'Jadwal dokter anak']);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.index', ['status' => 'new', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Jadwal dokter anak')
            ->assertDontSee('situs-lain.test');

        $this->get(route('admin.messages.index', ['status' => 'zzz']))
            ->assertOk()->assertSee('Jadwal dokter anak');

        $this->get(route('admin.messages.index', ['status' => ['x'], 'q' => ['x'], 'urut' => ['x'], 'per_page' => ['x']]))
            ->assertOk();
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('Belum ada pesan')
            ->assertSee('Pesan dari formulir kontak akan tampil di sini.');
    }

    // --- Escape output (SEK-15) -----------------------------------------------------------------------

    public function test_sender_text_is_escaped_in_the_list_and_the_detail_sek15(): void
    {
        $message = $this->message([
            'name' => '<script>alert(1)</script>',
            'subject' => '<script>alert(2)</script> Halo',
            'message' => "<script>alert(3)</script>\n<img src=x onerror=alert(4)>",
        ]);
        $this->actingAs($this->createStaff('registration-officer'));

        foreach ([route('admin.messages.index'), route('admin.messages.show', $message)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertDontSee('<script>alert(2)</script>', false)
                ->assertDontSee('<script>alert(3)</script>', false)
                ->assertDontSee('<img src=x', false)
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
                ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false);
        }

        $this->get(route('admin.messages.show', $message))
            ->assertSee('&lt;script&gt;alert(3)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(4)&gt;', false);
    }

    // --- Detail dan status (F4B-R24) -----------------------------------------------------------------

    public function test_opening_a_new_message_marks_it_read_once_with_a_single_audit(): void
    {
        $message = $this->message();
        $user = $this->createStaff('registration-officer');
        $this->actingAs($user);

        $this->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertSee('<i class="bi bi-envelope-open" aria-hidden="true"></i> Dibaca', false);

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Read, $message->status);
        $this->assertNull($message->handled_by);
        $this->assertNull($message->handled_at);

        $this->get(route('admin.messages.show', $message))->assertOk();
        $this->get(route('admin.messages.show', $message))->assertOk();

        $log = AuditLog::query()->where('action', 'contact_message.status_changed')->sole();

        $this->assertSame(['status' => 'new'], $log->old_values);
        $this->assertSame(['status' => 'read'], $log->new_values);
        $this->assertSame('Status pesan kontak #'.$message->id.' diubah dari Baru menjadi Dibaca.', $log->description);
        $this->assertSame(ContactMessage::class, $log->auditable_type);
        $this->assertSame($message->id, $log->auditable_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(ActorType::Staff, $log->actor_type);
    }

    public function test_opening_a_message_that_is_not_new_changes_nothing(): void
    {
        $handler = $this->createStaff('super-admin');
        $replied = $this->message(['status' => ContactMessageStatus::Replied, 'handled_by' => $handler->id, 'handled_at' => now()->subDay()]);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.show', $replied))->assertOk();

        $this->assertSame(ContactMessageStatus::Replied, $replied->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    public function test_the_detail_shows_metadata_links_body_lines_and_the_manual_reply_hint(): void
    {
        $handler = $this->createStaff('super-admin', ['name' => 'Rina Penangan']);
        $message = $this->message([
            'name' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'phone' => '0812-3456-7890',
            'subject' => 'Jadwal dokter anak',
            'message' => "Baris pertama pesan.\nBaris kedua pesan.",
            'status' => ContactMessageStatus::Replied,
            'consent_at' => now()->subDay(),
            'handled_by' => $handler->id,
            'handled_at' => now()->subHour(),
        ]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertSee('Pesan: Jadwal dokter anak')
            ->assertSee('Pesan Kontak')
            ->assertSee('Budi Santoso')
            ->assertSee('href="mailto:budi@contoh.test"', false)
            ->assertSee('href="tel:081234567890"', false)
            ->assertSee('0812-3456-7890')
            ->assertSee('5 Okt 2026, 10.30')
            ->assertSee('<i class="bi bi-reply" aria-hidden="true"></i> Dibalas', false)
            ->assertSee('oleh Rina Penangan pada 6 Okt 2026, 09.30')
            ->assertSee('Baris pertama pesan.')
            ->assertSee('Baris kedua pesan.')
            ->assertSee('Balas pesan ini secara manual lewat email atau telepon. Aplikasi tidak mengirim balasan otomatis.')
            ->assertSee('Kembali ke daftar pesan')
            ->assertSee('href="'.route('admin.messages.index').'"', false);
    }

    public function test_the_detail_hides_the_phone_row_when_there_is_no_phone_and_shows_no_handler_for_unhandled_messages(): void
    {
        $message = $this->message(['phone' => null, 'status' => ContactMessageStatus::Read]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertDontSee('href="tel:', false)
            ->assertDontSee('Ditangani');
    }

    /**
     * @return array<string, array{ContactMessageStatus, list<string>, list<string>}>
     */
    public static function statusButtons(): array
    {
        return [
            'dibaca' => [ContactMessageStatus::Read, ['Tandai Dibalas', 'Arsipkan'], ['Pulihkan']],
            'dibalas' => [ContactMessageStatus::Replied, ['Arsipkan'], ['Tandai Dibalas', 'Pulihkan']],
            'diarsipkan' => [ContactMessageStatus::Archived, ['Pulihkan'], ['Tandai Dibalas', 'Arsipkan']],
        ];
    }

    /**
     * @param  list<string>  $shown
     * @param  list<string>  $hidden
     */
    #[DataProvider('statusButtons')]
    public function test_the_detail_offers_only_the_valid_status_buttons(ContactMessageStatus $status, array $shown, array $hidden): void
    {
        $message = $this->message(['status' => $status]);
        $response = $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertSee('action="'.$this->statusUrl($message).'"', false);

        foreach ($shown as $label) {
            $response->assertSee($label);
        }

        foreach ($hidden as $label) {
            $response->assertDontSee($label);
        }
    }

    public function test_marking_replied_fills_the_handler_and_the_time_with_one_audit_and_the_flash(): void
    {
        $message = $this->message(['status' => ContactMessageStatus::Read]);
        $user = $this->createStaff('registration-officer');

        $this->actingAs($user)
            ->from(route('admin.messages.show', $message))
            ->patch($this->statusUrl($message), ['status' => 'replied'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.messages.show', $message))
            ->assertSessionHas('success', 'Status pesan berhasil diperbarui.');

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Replied, $message->status);
        $this->assertSame($user->id, $message->handled_by);
        $this->assertTrue($message->handled_at->equalTo(now()));

        $log = AuditLog::query()->where('action', 'contact_message.status_changed')->sole();
        $this->assertSame(['status' => 'read'], $log->old_values);
        $this->assertSame(['status' => 'replied'], $log->new_values);
        $this->assertSame('Status pesan kontak #'.$message->id.' diubah dari Dibaca menjadi Dibalas.', $log->description);
    }

    public function test_archiving_then_restoring_keeps_the_handler_and_replied_to_archived_records_the_new_handler(): void
    {
        $first = $this->createStaff('registration-officer');
        $second = $this->createStaff('super-admin');
        $message = $this->message(['status' => ContactMessageStatus::Replied]);

        $this->actingAs($first)->patch($this->statusUrl($message), ['status' => 'archived'])->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Archived, $message->status);
        $this->assertSame($first->id, $message->handled_by);
        $stamp = $message->handled_at->toDateTimeString();
        $this->assertSame('2026-10-06 10:30:00', $stamp);

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->app['auth']->forgetGuards();

        $this->actingAs($second)->patch($this->statusUrl($message), ['status' => 'read'])->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Read, $message->status);
        $this->assertSame($first->id, $message->handled_by);
        $this->assertSame($stamp, $message->handled_at->toDateTimeString());

        $this->patch($this->statusUrl($message), ['status' => 'archived'])->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertSame($second->id, $message->handled_by);
        $this->assertSame('2026-10-07 09:00:00', $message->handled_at->toDateTimeString());
        $this->assertSame(3, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    public function test_a_new_message_can_be_archived_directly_without_being_opened(): void
    {
        $message = $this->message();
        $user = $this->createStaff('registration-officer');

        $this->actingAs($user)->patch($this->statusUrl($message), ['status' => 'archived'])->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Archived, $message->status);
        $this->assertSame($user->id, $message->handled_by);
        $this->assertSame(
            'Status pesan kontak #'.$message->id.' diubah dari Baru menjadi Diarsipkan.',
            AuditLog::query()->where('action', 'contact_message.status_changed')->sole()->description,
        );
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidStatuses(): array
    {
        return [
            'kembali ke baru' => ['new', 'Status pesan tidak valid.'],
            'tidak dikenal' => ['selesai', 'Status pesan tidak valid.'],
            'huruf besar' => ['READ', 'Status pesan tidak valid.'],
            'kosong' => ['', 'Status pesan wajib dipilih.'],
            'larik' => [['read'], 'Status pesan tidak valid.'],
        ];
    }

    #[DataProvider('invalidStatuses')]
    public function test_invalid_statuses_are_rejected_with_indonesian_messages_and_change_nothing(mixed $value, string $error): void
    {
        $message = $this->message(['status' => ContactMessageStatus::Read]);
        $show = route('admin.messages.show', $message);

        $this->actingAs($this->createStaff('registration-officer'))
            ->from($show)
            ->patch($this->statusUrl($message), ['status' => $value])
            ->assertRedirect($show)
            ->assertSessionHasErrors(['status' => $error]);

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Read, $message->status);
        $this->assertNull($message->handled_by);
        $this->assertSame(0, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    public function test_a_missing_status_field_is_rejected(): void
    {
        $message = $this->message(['status' => ContactMessageStatus::Read]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->from(route('admin.messages.show', $message))
            ->patch($this->statusUrl($message), [])
            ->assertSessionHasErrors(['status' => 'Status pesan wajib dipilih.']);
    }

    public function test_the_same_status_changes_nothing_writes_no_audit_but_still_flashes(): void
    {
        $handler = $this->createStaff('super-admin');
        $stamp = Carbon::parse('2026-10-05 08:00:00');
        $read = $this->message(['status' => ContactMessageStatus::Read]);
        $replied = $this->message(['status' => ContactMessageStatus::Replied, 'handled_by' => $handler->id, 'handled_at' => $stamp]);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->patch($this->statusUrl($read), ['status' => 'read'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Status pesan berhasil diperbarui.');

        $this->patch($this->statusUrl($replied), ['status' => 'replied'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Status pesan berhasil diperbarui.');

        $this->assertNull($read->fresh()->handled_by);
        $replied->refresh();
        $this->assertSame($handler->id, $replied->handled_by);
        $this->assertSame($stamp->toDateTimeString(), $replied->handled_at->toDateTimeString());
        $this->assertSame(0, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    // --- Privasi (Review Focus 5) ---------------------------------------------------------------------

    public function test_audit_rows_never_contain_the_sender_name_email_phone_subject_or_message(): void
    {
        $message = $this->message([
            'name' => 'Rahasia Pengirim',
            'email' => 'rahasia@pengirim.test',
            'phone' => '081234567890',
            'subject' => 'Subjek Rahasia',
            'message' => 'Isi pesan yang rahasia sekali.',
        ]);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.show', $message))->assertOk();
        $this->patch($this->statusUrl($message), ['status' => 'replied'])->assertSessionHasNoErrors();
        $this->patch($this->statusUrl($message), ['status' => 'archived'])->assertSessionHasNoErrors();

        $logs = AuditLog::query()->where('action', 'like', 'contact_message.%')->get();
        $this->assertCount(3, $logs);
        $this->assertSame(['contact_message.status_changed'], $logs->pluck('action')->unique()->values()->all());

        $dump = json_encode($logs->map->toArray()->all(), JSON_UNESCAPED_UNICODE);

        foreach (['Rahasia Pengirim', 'rahasia@pengirim.test', '081234567890', 'Subjek Rahasia', 'Isi pesan yang rahasia'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $dump);
        }
    }

    public function test_creating_a_message_does_not_write_an_audit_row(): void
    {
        $this->message(['name' => 'Rahasia Pengirim']);

        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'contact_message.%')->count());
    }

    public function test_the_status_route_is_rejected_for_a_user_with_the_wrong_permission(): void
    {
        $message = $this->message(['status' => ContactMessageStatus::Read]);
        $user = $this->createStaff('content-admin');
        $user->syncRoles([]);
        $user->givePermissionTo('rooms.update-availability');

        $this->actingAs($user)
            ->patch($this->statusUrl($message), ['status' => 'archived'])
            ->assertForbidden();

        $this->assertSame(ContactMessageStatus::Read, $message->fresh()->status);
    }
}
