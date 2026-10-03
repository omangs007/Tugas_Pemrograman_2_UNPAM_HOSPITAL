<?php

namespace Tests\Feature\Settings;

use App\Models\WebsiteSetting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Salinan literal 00 E.7 (nilai) + 13 §8.9 (type, group, label); urutan = urutan 00 E.7.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: ?string}>
     */
    public static function defaults(): array
    {
        return [
            'site.name' => ['site.name', 'string', 'identitas', 'Nama situs', 'UNPAM HOSPITAL'],
            'site.tagline' => ['site.tagline', 'string', 'identitas', 'Tagline', 'Layanan kesehatan yang ramah dan terpercaya'],
            'site.logo_media_id' => ['site.logo_media_id', 'int', 'identitas', 'Logo situs (kosong = placeholder bawaan)', null],
            'contact.address' => ['contact.address', 'text', 'kontak', 'Alamat', 'Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310'],
            'contact.phone' => ['contact.phone', 'string', 'kontak', 'Telepon (placeholder)', '(021) 0000-0000'],
            'contact.emergency_phone' => ['contact.emergency_phone', 'string', 'kontak', 'Telepon darurat (placeholder)', '(021) 0000-0119'],
            'contact.whatsapp' => ['contact.whatsapp', 'string', 'kontak', 'Nomor WhatsApp (format 62…, placeholder)', '6280000000000'],
            'contact.email' => ['contact.email', 'email', 'kontak', 'E-mail kontak (placeholder)', 'info@unpamhospital.test'],
            'contact.operational_hours' => ['contact.operational_hours', 'string', 'kontak', 'Jam operasional', 'Senin–Sabtu 08.00–20.00 (contoh)'],
            'map.directions_url' => ['map.directions_url', 'url', 'kontak', 'Tautan Petunjuk Arah', 'https://www.google.com/maps/search/?api=1&query=Universitas+Pamulang+Kampus+2+Viktor'],
            'map.embed_url' => ['map.embed_url', 'url', 'kontak', 'Tautan peta tersemat', 'https://www.google.com/maps?q=Universitas+Pamulang+Kampus+2+Viktor&output=embed'],
            'booking.window_days' => ['booking.window_days', 'int', 'booking', 'Jendela pendaftaran (hari)', '30'],
            'booking.online_cutoff_minutes' => ['booking.online_cutoff_minutes', 'int', 'booking', 'Batas pendaftaran online (menit sebelum sesi)', '60'],
            'booking.cancel_cutoff_minutes' => ['booking.cancel_cutoff_minutes', 'int', 'booking', 'Batas pembatalan pasien (menit sebelum sesi)', '120'],
            'booking.checkin_open_minutes' => ['booking.checkin_open_minutes', 'int', 'booking', 'Check-in dibuka (menit sebelum sesi)', '60'],
            'booking.max_active_per_patient' => ['booking.max_active_per_patient', 'int', 'booking', 'Maks. pendaftaran aktif per pasien', '3'],
            'booking.auto_confirm' => ['booking.auto_confirm', 'bool', 'booking', 'Konfirmasi otomatis', '0'],
            'seo.default_meta_description' => ['seo.default_meta_description', 'text', 'seo', 'Meta description bawaan', 'UNPAM HOSPITAL — informasi layanan, dokter, jadwal praktik, dan pendaftaran kunjungan rawat jalan (situs prototipe).'],
            'privacy.consent_version' => ['privacy.consent_version', 'string', 'privasi', 'Versi persetujuan privasi', '1.0'],
        ];
    }

    #[DataProvider('defaults')]
    public function test_each_default_matches_the_blueprint(string $key, string $type, string $group, string $label, ?string $value): void
    {
        $this->seed(SettingsSeeder::class);

        $setting = WebsiteSetting::query()->where('key', $key)->firstOrFail();

        $this->assertSame($type, $setting->type);
        $this->assertSame($group, $setting->group);
        $this->assertSame($label, $setting->label);
        $this->assertSame($value, $setting->value);
        $this->assertNull($setting->updated_by);
    }

    public function test_exactly_the_19_blueprint_keys_are_seeded_in_blueprint_order(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertCount(19, SettingsSeeder::DEFAULTS);
        $this->assertSame(array_keys(self::defaults()), array_keys(SettingsSeeder::DEFAULTS));
        $this->assertSame(array_keys(self::defaults()), WebsiteSetting::query()->orderBy('id')->pluck('key')->all());
    }

    public function test_defaults_constant_has_the_four_fields_with_known_types_and_groups(): void
    {
        foreach (SettingsSeeder::DEFAULTS as $key => $setting) {
            $this->assertSame(['type', 'group', 'label', 'value'], array_keys($setting), $key);
            $this->assertContains($setting['type'], ['string', 'text', 'int', 'bool', 'url', 'email', 'json'], $key);
            $this->assertContains($setting['group'], ['identitas', 'kontak', 'booking', 'seo', 'privasi'], $key);
        }
    }

    public function test_groups_have_the_blueprint_sizes(): void
    {
        $this->seed(SettingsSeeder::class);

        $counts = WebsiteSetting::query()->selectRaw('`group`, count(*) as total')->groupBy('group')->pluck('total', 'group')->map(fn ($total): int => (int) $total)->all();
        ksort($counts);

        $this->assertSame(['booking' => 6, 'identitas' => 3, 'kontak' => 8, 'privasi' => 1, 'seo' => 1], $counts);
    }

    public function test_seeding_twice_is_idempotent(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->assertSame(19, WebsiteSetting::query()->count());
    }

    public function test_seeding_does_not_overwrite_values_changed_by_an_admin_but_restores_deleted_keys(): void
    {
        $this->seed(SettingsSeeder::class);

        WebsiteSetting::query()->where('key', 'site.name')->update(['value' => 'RS Pamulang']);
        WebsiteSetting::query()->where('key', 'booking.window_days')->delete();

        $this->seed(SettingsSeeder::class);

        $this->assertSame('RS Pamulang', WebsiteSetting::query()->where('key', 'site.name')->value('value'));
        $this->assertSame('30', WebsiteSetting::query()->where('key', 'booking.window_days')->value('value'));
        $this->assertSame(19, WebsiteSetting::query()->count());
    }
}
