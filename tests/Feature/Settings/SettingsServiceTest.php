<?php

namespace Tests\Feature\Settings;

use App\Models\WebsiteSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);

        $this->settings = app(SettingsService::class);
    }

    /**
     * Ubah baris langsung di database (melewati service) lalu buang cache.
     */
    private function rawUpdate(string $key, ?string $value): void
    {
        WebsiteSetting::query()->where('key', $key)->update(['value' => $value]);

        $this->settings->forget();
    }

    public function test_service_is_a_singleton(): void
    {
        $this->assertSame($this->settings, app(SettingsService::class));
    }

    public function test_typed_getters_read_the_seeded_defaults(): void
    {
        $this->assertSame('UNPAM HOSPITAL', $this->settings->string('site.name'));
        $this->assertSame('UNPAM HOSPITAL', $this->settings->get('site.name'));
        $this->assertSame(30, $this->settings->int('booking.window_days'));
        $this->assertSame(3, $this->settings->get('booking.max_active_per_patient'));
        $this->assertFalse($this->settings->bool('booking.auto_confirm'));
        $this->assertSame('(021) 0000-0000', $this->settings->string('contact.phone'));
    }

    public function test_unknown_keys_return_the_default(): void
    {
        $this->assertNull($this->settings->get('tidak.ada'));
        $this->assertSame('x', $this->settings->get('tidak.ada', 'x'));
        $this->assertSame('d', $this->settings->string('tidak.ada', 'd'));
        $this->assertSame(7, $this->settings->int('tidak.ada', 7));
        $this->assertTrue($this->settings->bool('tidak.ada', true));
    }

    public function test_empty_values_fall_back_to_the_default(): void
    {
        $this->assertSame(7, $this->settings->int('site.logo_media_id', 7));
        $this->assertSame('x', $this->settings->string('site.logo_media_id', 'x'));
        $this->assertNull($this->settings->get('site.logo_media_id'));

        $this->rawUpdate('site.tagline', '');

        $this->assertSame('Cadangan', $this->settings->string('site.tagline', 'Cadangan'));
    }

    public function test_bool_zero_is_false_even_when_the_default_is_true(): void
    {
        $this->assertFalse($this->settings->bool('booking.auto_confirm', true));

        $this->rawUpdate('booking.auto_confirm', '1');
        $this->assertTrue($this->settings->bool('booking.auto_confirm'));

        $this->rawUpdate('booking.auto_confirm', 'true');
        $this->assertTrue($this->settings->bool('booking.auto_confirm'));

        $this->rawUpdate('booking.auto_confirm', 'false');
        $this->assertFalse($this->settings->bool('booking.auto_confirm', true));
    }

    public function test_int_zero_is_a_real_value_not_the_default(): void
    {
        $this->rawUpdate('booking.online_cutoff_minutes', '0');

        $this->assertSame(0, $this->settings->int('booking.online_cutoff_minutes', 60));
    }

    public function test_json_type_is_decoded_and_invalid_json_falls_back_to_the_default(): void
    {
        WebsiteSetting::query()->create(['key' => 'uji.json', 'value' => '{"a":1,"b":[2,3]}', 'type' => 'json', 'group' => 'seo', 'label' => 'Uji JSON']);
        $this->settings->forget();

        $this->assertSame(['a' => 1, 'b' => [2, 3]], $this->settings->get('uji.json'));

        $this->rawUpdate('uji.json', '{rusak');

        $this->assertSame('cadangan', $this->settings->get('uji.json', 'cadangan'));
    }

    public function test_group_returns_items_in_id_order_with_typed_values(): void
    {
        $kontak = $this->settings->group('kontak');

        $this->assertSame(
            ['contact.address', 'contact.phone', 'contact.emergency_phone', 'contact.whatsapp', 'contact.email', 'contact.operational_hours', 'map.directions_url', 'map.embed_url'],
            $kontak->pluck('key')->all(),
        );
        $this->assertSame(['key', 'label', 'type', 'value'], array_keys($kontak->first()));
        $this->assertSame('Alamat', $kontak->first()['label']);
        $this->assertSame('text', $kontak->first()['type']);

        $booking = $this->settings->group('booking')->keyBy('key');
        $this->assertCount(6, $booking);
        $this->assertSame(30, $booking['booking.window_days']['value']);
        $this->assertFalse($booking['booking.auto_confirm']['value']);

        $this->assertNull($this->settings->group('identitas')->firstWhere('key', 'site.logo_media_id')['value']);
        $this->assertCount(3, $this->settings->group('identitas'));
        $this->assertCount(1, $this->settings->group('seo'));
        $this->assertCount(1, $this->settings->group('privasi'));
        $this->assertCount(0, $this->settings->group('tidak-ada'));
    }

    public function test_settings_are_cached_after_the_first_read(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->settings->get('site.name');
        $this->settings->int('booking.window_days');
        $this->settings->bool('booking.auto_confirm');

        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'website_settings'));

        $this->assertCount(1, $queries);
    }

    public function test_another_instance_reads_from_the_shared_cache_without_querying(): void
    {
        $this->settings->get('site.name');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $other = new SettingsService(app(AuditLogger::class));

        $this->assertSame('UNPAM HOSPITAL', $other->string('site.name'));
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_logo_url_falls_back_to_the_placeholder_when_unset(): void
    {
        $this->assertSame(asset('images/placeholders/logo.svg'), $this->settings->logoUrl());
        $this->assertStringEndsWith('/images/placeholders/logo.svg', $this->settings->logoUrl());
    }

    public function test_logo_url_uses_the_logo_media_when_set(): void
    {
        $media = app(MediaService::class)->store(ImageFixtures::transparentPng(300, 100), 'logo', null, ['alt_text' => 'Logo']);
        $this->rawUpdate('site.logo_media_id', (string) $media->id);

        $this->assertSame($media->url, $this->settings->logoUrl());
        $this->assertStringContainsString('/storage/logo/', $this->settings->logoUrl());
    }

    public function test_logo_url_falls_back_when_the_media_was_deleted_or_is_private(): void
    {
        $media = app(MediaService::class)->store(ImageFixtures::transparentPng(300, 100), 'logo', null, ['alt_text' => 'Logo']);
        $this->rawUpdate('site.logo_media_id', (string) $media->id);

        $media->forceFill(['disk' => 'local'])->save();
        $this->assertSame(asset('images/placeholders/logo.svg'), $this->settings->logoUrl());

        $media->forceFill(['disk' => 'public'])->save();
        app(MediaService::class)->delete($media);
        $this->assertSame(asset('images/placeholders/logo.svg'), $this->settings->logoUrl());
    }

    public function test_whatsapp_url_uses_the_configured_number(): void
    {
        $this->assertSame('https://wa.me/6280000000000', $this->settings->whatsappUrl());
        $this->assertSame('https://wa.me/6280000000000?text=Halo%2C%20saya%20ingin%20bertanya', $this->settings->whatsappUrl('Halo, saya ingin bertanya'));
        $this->assertSame('https://wa.me/6280000000000', $this->settings->whatsappUrl('   '));
    }

    public function test_whatsapp_url_keeps_digits_only_and_is_null_when_empty(): void
    {
        $this->rawUpdate('contact.whatsapp', '+62 811-2222-3333');
        $this->assertSame('https://wa.me/6281122223333', $this->settings->whatsappUrl());

        $this->rawUpdate('contact.whatsapp', null);
        $this->assertNull($this->settings->whatsappUrl());
        $this->assertNull($this->settings->whatsappUrl('Halo'));
    }
}
