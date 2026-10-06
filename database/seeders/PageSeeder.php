<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /** Penanda alamat pada isi `about`; diganti `contact.address` (di-escape) saat seeding. */
    public const ADDRESS_TOKEN = '{alamat}';

    /**
     * Isi rich text halaman sistem (13 §8.8, 12 §7.6). Hanya tag yang diizinkan profil `rich_text`; tanpa
     * tautan, kutip, apostrof, dan tanpa klaim akreditasi atau kerja sama resmi (BR-142). Kotak peringatan
     * `insurance-info` ditampilkan view #16, bukan bagian dari isi ini.
     *
     * @var array<string, string>
     */
    public const BODIES = [
        'about' => '<p>UNPAM HOSPITAL adalah rumah sakit <strong>prototipe</strong> yang berlokasi di {alamat}.</p>'.
            '<p>Situs ini memperlihatkan informasi layanan, dokter, jadwal praktik, dan pendaftaran kunjungan rawat jalan.</p>'.
            '<p>Seluruh data pada situs ini adalah contoh dan akan diganti dengan data resmi oleh rumah sakit.</p>',

        'vision-mission' => '<h2>Visi</h2>'.
            '<p>Menjadi layanan kesehatan yang ramah, mudah diakses, dan mengutamakan keselamatan pasien.</p>'.
            '<h2>Misi</h2>'.
            '<ol>'.
            '<li>Memberikan pelayanan yang ramah dan tertib.</li>'.
            '<li>Menyediakan informasi jadwal yang jelas dan mutakhir.</li>'.
            '<li>Menjaga kerahasiaan data pasien.</li>'.
            '<li>Mempermudah pendaftaran kunjungan.</li>'.
            '</ol>'.
            '<p><em>Visi dan misi di atas adalah contoh untuk prototipe dan dapat diganti oleh admin.</em></p>',

        'insurance-info' => '<h2>BPJS Kesehatan</h2>'.
            '<p>Pilih jenis pembayaran BPJS Kesehatan saat mendaftar kunjungan, lalu isi nomor peserta 13 digit. '.
            'Bawa kartu atau identitas peserta serta dokumen lain sesuai ketentuan yang berlaku saat datang ke rumah sakit.</p>'.
            '<h2>Asuransi</h2>'.
            '<p>Pilih mitra asuransi dari daftar pada halaman ini (contoh), lalu bawa kartu atau polis asuransi Anda saat datang.</p>'.
            '<h2>Pembayaran Umum</h2>'.
            '<p>Pasien yang membayar sendiri memilih jenis pembayaran Umum. '.
            'Rincian biaya mengikuti ketentuan rumah sakit yang berlaku dan dapat ditanyakan melalui kontak rumah sakit.</p>',

        'registration-guide' => '<h2>Pendaftaran Online</h2>'.
            '<ol>'.
            '<li>Buat akun melalui menu Daftar Akun.</li>'.
            '<li>Masuk dengan e-mail dan kata sandi Anda.</li>'.
            '<li>Pilih poliklinik yang Anda tuju.</li>'.
            '<li>Pilih dokter, tanggal, dan sesi praktik.</li>'.
            '<li>Isi data pendaftaran, lalu konfirmasi.</li>'.
            '<li>Simpan bukti pendaftaran dan kode booking Anda.</li>'.
            '</ol>'.
            '<h2>Pendaftaran di Loket</h2>'.
            '<p>Anda juga dapat datang langsung ke loket pendaftaran. Petugas membantu membuat profil pasien dan mendaftarkan kunjungan '.
            'selama sesi praktik belum berakhir dan kuota masih tersedia. Kuota dibagi bersama pendaftaran online.</p>'.
            '<h2>Batas Waktu</h2>'.
            '<ul>'.
            '<li>Pendaftaran online ditutup 60 menit sebelum sesi dimulai.</li>'.
            '<li>Pembatalan oleh pasien paling lambat 120 menit sebelum sesi dimulai.</li>'.
            '<li>Check-in dibuka 60 menit sebelum sesi dimulai.</li>'.
            '</ul>'.
            '<p>Nomor antrean tidak dipakai ulang bila pendaftaran dibatalkan. Angka batas waktu di atas adalah pengaturan awal dan dapat disesuaikan rumah sakit.</p>',

        'privacy-policy' => '<h2>1. Pengelola dan kontak</h2>'.
            '<p>Pengelola data pada situs ini adalah UNPAM HOSPITAL. Situs ini adalah prototipe dan seluruh data di dalamnya adalah contoh. '.
            'Kontak pengelola tercantum pada bagian bawah setiap halaman.</p>'.
            '<h2>2. Data yang dikumpulkan dan yang tidak dikumpulkan</h2>'.
            '<p>Untuk pendaftaran kunjungan, kami mengumpulkan nama, tanggal lahir, jenis kelamin, nomor telepon, e-mail, alamat, '.
            'nomor BPJS atau nomor polis asuransi bila Anda memilihnya, serta keluhan singkat bila Anda mengisinya.</p>'.
            '<p>Kami tidak meminta NIK maupun data medis.</p>'.
            '<h2>3. Tujuan pemrosesan</h2>'.
            '<ul>'.
            '<li>pendaftaran kunjungan rawat jalan</li>'.
            '<li>penjadwalan dan antrean</li>'.
            '<li>komunikasi terkait jadwal</li>'.
            '<li>keamanan akun</li>'.
            '</ul>'.
            '<h2>4. Dasar pemrosesan dan persetujuan</h2>'.
            '<p>Data diproses berdasarkan persetujuan Anda saat membuat akun atau saat didaftarkan oleh petugas di loket. '.
            'Persetujuan dicatat bersama nomor versi kebijakan ini.</p>'.
            '<h2>5. Pembagian data</h2>'.
            '<p>Data tidak dijual dan tidak dibagikan kepada pihak ketiga pada tahap ini. '.
            'Tidak ada integrasi dengan BPJS, SIMRS, SATUSEHAT, maupun WhatsApp API. '.
            'Peta Google dimuat dari pihak ketiga hanya bila Anda terhubung ke internet, dan peta itu tidak mengirim data pasien.</p>'.
            '<h2>6. Keamanan</h2>'.
            '<p>Nomor BPJS dan nomor polis disimpan terenkripsi. Akses dibatasi menurut peran, aktivitas penting dicatat dalam jejak audit, '.
            'dan percobaan masuk dibatasi jumlahnya. Admin konten tidak dapat melihat data pasien.</p>'.
            '<h2>7. Penyimpanan dan retensi</h2>'.
            '<p>Data disimpan selama diperlukan untuk layanan pendaftaran. Ketentuan lama penyimpanan akan ditetapkan rumah sakit '.
            'sebelum situs ini dipakai secara resmi.</p>'.
            '<h2>8. Hak pemilik data</h2>'.
            '<p>Anda dapat melihat dan memperbaiki data melalui profil Anda, meminta penonaktifan atau penghapusan data melalui rumah sakit, '.
            'serta menarik persetujuan Anda.</p>'.
            '<h2>9. Cookie</h2>'.
            '<p>Situs ini hanya memakai cookie sesi dan token CSRF yang diperlukan agar situs berfungsi. '.
            'Tidak ada pelacak iklan maupun analitik pihak ketiga.</p>'.
            '<h2>10. Anak dan orang di bawah pengampuan</h2>'.
            '<p>Portal online ditujukan bagi pasien dewasa. Pendaftaran untuk anak atau orang di bawah pengampuan dilakukan di loket '.
            'oleh orang tua atau wali.</p>'.
            '<h2>11. Perubahan kebijakan</h2>'.
            '<p>Kebijakan ini dapat berubah. Setiap perubahan diberi nomor versi baru. Versi saat ini adalah 1.0.</p>'.
            '<h2>12. Menghubungi rumah sakit</h2>'.
            '<p>Untuk permintaan terkait privasi, hubungi rumah sakit melalui kontak yang tercantum pada bagian bawah setiap halaman.</p>'.
            '<p><em>Teks contoh; tinjau dengan konsultan hukum sebelum produksi.</em></p>',
    ];

    /**
     * Menyemai 15 halaman sistem (13 §8.8) di semua lingkungan. Idempoten per key: baris yang sudah ada
     * dilewati sehingga suntingan admin tidak tertimpa. `forceFill()` + `save()` agar `is_system` terisi
     * dan sanitasi rich text (SanitizesRichText) serta audit berjalan.
     */
    public function run(SettingsService $settings): void
    {
        $address = $settings->string('contact.address', (string) SettingsSeeder::DEFAULTS['contact.address']['value']);

        foreach (Page::KEYS as $key) {
            if (Page::query()->where('key', $key)->exists()) {
                continue;
            }

            $defaults = Page::DEFAULTS[$key];
            $body = self::BODIES[$key] ?? null;

            if ($key === 'about' && $body !== null) {
                $body = str_replace(self::ADDRESS_TOKEN, e($address), $body);
            }

            (new Page)->forceFill([
                'key' => $key,
                'title' => $defaults['title'],
                'subtitle' => $defaults['subtitle'],
                'body' => $body,
                'meta_title' => null,
                'meta_description' => $defaults['meta_description'],
                'is_system' => true,
                'updated_by' => null,
            ])->save();
        }
    }
}
