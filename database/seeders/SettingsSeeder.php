<?php

namespace Database\Seeders;

use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * 19 kunci baku: nilai default 00 E.7; type, group, dan label 13 §8.9.
     * Nilai disimpan sebagai string (bool false = '0'); NULL = kosong (pakai default aplikasi).
     *
     * @var array<string, array{type: string, group: string, label: string, value: ?string}>
     */
    public const DEFAULTS = [
        'site.name' => ['type' => 'string', 'group' => 'identitas', 'label' => 'Nama situs', 'value' => 'UNPAM HOSPITAL'],
        'site.tagline' => ['type' => 'string', 'group' => 'identitas', 'label' => 'Tagline', 'value' => 'Layanan kesehatan yang ramah dan terpercaya'],
        'site.logo_media_id' => ['type' => 'int', 'group' => 'identitas', 'label' => 'Logo situs (kosong = placeholder bawaan)', 'value' => null],
        'contact.address' => ['type' => 'text', 'group' => 'kontak', 'label' => 'Alamat', 'value' => 'Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310'],
        'contact.phone' => ['type' => 'string', 'group' => 'kontak', 'label' => 'Telepon (placeholder)', 'value' => '(021) 0000-0000'],
        'contact.emergency_phone' => ['type' => 'string', 'group' => 'kontak', 'label' => 'Telepon darurat (placeholder)', 'value' => '(021) 0000-0119'],
        'contact.whatsapp' => ['type' => 'string', 'group' => 'kontak', 'label' => 'Nomor WhatsApp (format 62…, placeholder)', 'value' => '6280000000000'],
        'contact.email' => ['type' => 'email', 'group' => 'kontak', 'label' => 'E-mail kontak (placeholder)', 'value' => 'info@unpamhospital.test'],
        'contact.operational_hours' => ['type' => 'string', 'group' => 'kontak', 'label' => 'Jam operasional', 'value' => 'Senin–Sabtu 08.00–20.00 (contoh)'],
        'map.directions_url' => ['type' => 'url', 'group' => 'kontak', 'label' => 'Tautan Petunjuk Arah', 'value' => 'https://www.google.com/maps/search/?api=1&query=Universitas+Pamulang+Kampus+2+Viktor'],
        'map.embed_url' => ['type' => 'url', 'group' => 'kontak', 'label' => 'Tautan peta tersemat', 'value' => 'https://www.google.com/maps?q=Universitas+Pamulang+Kampus+2+Viktor&output=embed'],
        'booking.window_days' => ['type' => 'int', 'group' => 'booking', 'label' => 'Jendela pendaftaran (hari)', 'value' => '30'],
        'booking.online_cutoff_minutes' => ['type' => 'int', 'group' => 'booking', 'label' => 'Batas pendaftaran online (menit sebelum sesi)', 'value' => '60'],
        'booking.cancel_cutoff_minutes' => ['type' => 'int', 'group' => 'booking', 'label' => 'Batas pembatalan pasien (menit sebelum sesi)', 'value' => '120'],
        'booking.checkin_open_minutes' => ['type' => 'int', 'group' => 'booking', 'label' => 'Check-in dibuka (menit sebelum sesi)', 'value' => '60'],
        'booking.max_active_per_patient' => ['type' => 'int', 'group' => 'booking', 'label' => 'Maks. pendaftaran aktif per pasien', 'value' => '3'],
        'booking.auto_confirm' => ['type' => 'bool', 'group' => 'booking', 'label' => 'Konfirmasi otomatis', 'value' => '0'],
        'seo.default_meta_description' => ['type' => 'text', 'group' => 'seo', 'label' => 'Meta description bawaan', 'value' => 'UNPAM HOSPITAL — informasi layanan, dokter, jadwal praktik, dan pendaftaran kunjungan rawat jalan (situs prototipe).'],
        'privacy.consent_version' => ['type' => 'string', 'group' => 'privasi', 'label' => 'Versi persetujuan privasi', 'value' => '1.0'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $setting) {
            WebsiteSetting::query()->firstOrCreate(['key' => $key], $setting);
        }
    }
}
