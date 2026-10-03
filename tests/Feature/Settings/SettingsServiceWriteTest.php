<?php

namespace Tests\Feature\Settings;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SettingsServiceWriteTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $settings;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        $this->settings = app(SettingsService::class);
        $this->staff = User::factory()->staff()->create();
    }

    private function stored(string $key): ?string
    {
        return WebsiteSetting::query()->where('key', $key)->value('value');
    }

    public function test_set_saves_the_value_and_it_is_readable_immediately(): void
    {
        $this->assertSame('UNPAM HOSPITAL', $this->settings->get('site.name'));

        $this->settings->set('site.name', 'RS Pamulang', $this->staff);

        $this->assertSame('RS Pamulang', $this->settings->get('site.name'));
        $this->assertSame('RS Pamulang', $this->stored('site.name'));
        $this->assertSame($this->staff->id, WebsiteSetting::query()->where('key', 'site.name')->value('updated_by'));
    }

    public function test_set_writes_one_settings_updated_audit_row_with_old_and_new_values(): void
    {
        $this->settings->set('site.name', 'RS Pamulang', $this->staff);

        $log = AuditLog::query()->where('action', 'settings.updated')->sole();

        $this->assertSame(WebsiteSetting::class, $log->auditable_type);
        $this->assertNull($log->auditable_id);
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->staff->id, $log->user_id);
        $this->assertSame(['site.name' => 'UNPAM HOSPITAL'], $log->old_values);
        $this->assertSame(['site.name' => 'RS Pamulang'], $log->new_values);
        $this->assertSame('Pengaturan situs diubah: site.name.', $log->description);
    }

    public function test_set_many_audits_only_the_keys_that_changed_in_one_row(): void
    {
        $this->settings->setMany([
            'site.name' => 'UNPAM HOSPITAL',
            'site.tagline' => 'Sehat bersama',
            'booking.window_days' => 45,
        ], $this->staff);

        $log = AuditLog::query()->where('action', 'settings.updated')->sole();

        $this->assertSame([
            'site.tagline' => 'Layanan kesehatan yang ramah dan terpercaya',
            'booking.window_days' => '30',
        ], $log->old_values);
        $this->assertSame(['site.tagline' => 'Sehat bersama', 'booking.window_days' => '45'], $log->new_values);

        $this->assertNull(WebsiteSetting::query()->where('key', 'site.name')->value('updated_by'));
        $this->assertSame($this->staff->id, WebsiteSetting::query()->where('key', 'booking.window_days')->value('updated_by'));
    }

    public function test_nothing_changed_writes_no_audit_and_touches_nothing(): void
    {
        $this->settings->setMany(['site.name' => 'UNPAM HOSPITAL', 'booking.window_days' => 30, 'booking.auto_confirm' => false], $this->staff);

        $this->assertSame(0, AuditLog::query()->where('action', 'settings.updated')->count());
        $this->assertNull(WebsiteSetting::query()->where('key', 'site.name')->value('updated_by'));
    }

    public function test_empty_set_many_is_a_no_op(): void
    {
        $this->settings->setMany([], $this->staff);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_set_without_a_user_audits_as_system(): void
    {
        $this->settings->set('site.tagline', 'Baru');

        $log = AuditLog::query()->where('action', 'settings.updated')->sole();

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertNull(WebsiteSetting::query()->where('key', 'site.tagline')->value('updated_by'));
    }

    public function test_unknown_key_throws_and_saves_nothing_from_the_same_call(): void
    {
        $thrown = null;

        try {
            $this->settings->setMany(['site.name' => 'Tidak boleh tersimpan', 'tidak.ada' => 'x'], $this->staff);
        } catch (InvalidArgumentException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame('Kunci pengaturan tidak dikenal: tidak.ada.', $thrown->getMessage());
        $this->assertSame('UNPAM HOSPITAL', $this->stored('site.name'));
        $this->assertSame(0, AuditLog::query()->where('action', 'settings.updated')->count());
        $this->assertNull(WebsiteSetting::query()->where('key', 'tidak.ada')->first());
    }

    public function test_values_are_serialized_by_type(): void
    {
        WebsiteSetting::query()->create(['key' => 'uji.json', 'value' => null, 'type' => 'json', 'group' => 'seo', 'label' => 'Uji JSON']);

        $this->settings->setMany([
            'booking.auto_confirm' => true,
            'booking.window_days' => '45',
            'booking.online_cutoff_minutes' => 90,
            'site.tagline' => '  Sehat bersama  ',
            'uji.json' => ['a' => 1, 'teks' => 'é/ü'],
        ]);

        $this->assertSame('1', $this->stored('booking.auto_confirm'));
        $this->assertSame('45', $this->stored('booking.window_days'));
        $this->assertSame('90', $this->stored('booking.online_cutoff_minutes'));
        $this->assertSame('Sehat bersama', $this->stored('site.tagline'));
        $this->assertSame('{"a":1,"teks":"é/ü"}', $this->stored('uji.json'));

        $this->assertTrue($this->settings->bool('booking.auto_confirm'));
        $this->assertSame(['a' => 1, 'teks' => 'é/ü'], $this->settings->get('uji.json'));

        $this->settings->set('booking.auto_confirm', false);
        $this->assertSame('0', $this->stored('booking.auto_confirm'));
        $this->assertFalse($this->settings->bool('booking.auto_confirm', true));
    }

    public function test_null_and_empty_strings_are_stored_as_null(): void
    {
        $this->settings->setMany(['site.tagline' => '', 'contact.emergency_phone' => null, 'site.logo_media_id' => null]);

        $this->assertNull($this->stored('site.tagline'));
        $this->assertNull($this->stored('contact.emergency_phone'));
        $this->assertSame('Cadangan', $this->settings->string('site.tagline', 'Cadangan'));
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: string}>
     */
    public static function invalidValues(): array
    {
        return [
            'int bukan angka' => ['booking.window_days', 'abc', 'Nilai pengaturan booking.window_days harus berupa angka bulat.'],
            'int berupa larik' => ['booking.window_days', [1], 'Nilai pengaturan booking.window_days harus berupa angka bulat.'],
            'string berupa larik' => ['site.name', ['x'], 'Nilai pengaturan site.name harus berupa teks.'],
            'json berupa teks' => ['uji.json', '{"a":1}', 'Nilai pengaturan uji.json harus berupa larik.'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_values_of_the_wrong_type_are_rejected_without_saving(string $key, mixed $value, string $message): void
    {
        WebsiteSetting::query()->create(['key' => 'uji.json', 'value' => null, 'type' => 'json', 'group' => 'seo', 'label' => 'Uji JSON']);
        $before = $this->stored($key);

        $thrown = null;

        try {
            $this->settings->setMany([$key => $value, 'site.tagline' => 'Tidak boleh tersimpan']);
        } catch (InvalidArgumentException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame($message, $thrown->getMessage());
        $this->assertSame($before, $this->stored($key));
        $this->assertSame('Layanan kesehatan yang ramah dan terpercaya', $this->stored('site.tagline'));
    }

    public function test_cache_is_forgotten_right_away_outside_a_transaction(): void
    {
        $this->settings->get('site.name');
        $this->assertTrue(Cache::has(SettingsService::CACHE_KEY));

        $this->settings->set('site.name', 'RS Pamulang');

        $this->assertFalse(Cache::has(SettingsService::CACHE_KEY));
    }

    public function test_cache_is_forgotten_only_after_the_surrounding_transaction_commits(): void
    {
        $this->settings->get('site.name');

        DB::transaction(function (): void {
            $this->settings->set('site.name', 'RS Pamulang');

            $this->assertTrue(Cache::has(SettingsService::CACHE_KEY));
        });

        $this->assertFalse(Cache::has(SettingsService::CACHE_KEY));
        $this->assertSame('RS Pamulang', $this->settings->get('site.name'));
    }

    public function test_cache_and_database_are_untouched_when_the_surrounding_transaction_rolls_back(): void
    {
        $this->settings->get('site.name');

        $thrown = null;

        try {
            DB::transaction(function (): void {
                $this->settings->set('site.name', 'RS Pamulang', $this->staff);

                throw new RuntimeException('simulasi galat');
            });
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertTrue(Cache::has(SettingsService::CACHE_KEY));
        $this->assertSame('UNPAM HOSPITAL', $this->stored('site.name'));
        $this->assertSame('UNPAM HOSPITAL', $this->settings->get('site.name'));
        $this->assertSame(0, AuditLog::query()->where('action', 'settings.updated')->count());
    }
}
