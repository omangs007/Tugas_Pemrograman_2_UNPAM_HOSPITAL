<?php

namespace Database\Seeders;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo pesan kontak (13 §10.4, F4B-R36). Hanya `local`/`testing`; idempoten per email dan subjek.
 * Pengirim fiktif (`@contoh.test`); `ip_hash` memakai IP 127.0.0.1 (IP mentah tidak disimpan).
 */
class ContactMessageSeeder extends Seeder
{
    /** Penangan pesan berstatus `replied` dan `archived` (DemoUserSeeder::ACCOUNTS, Petugas Pendaftaran Demo). */
    public const HANDLER_EMAIL = 'pendaftaran@unpamhospital.test';

    /**
     * 13 §10.4. Urutan = nomor #1…5; `created_at` = sekarang dikurangi (6 - nomor) hari dan `consent_at` = `created_at`.
     * `handled` true (#4, #5): `handled_by` = Petugas Pendaftaran Demo dan `handled_at` = `created_at` + 1 jam.
     *
     * @var list<array{name: string, email: string, phone: ?string, subject: string, message: string, status: string, handled: bool}>
     */
    public const MESSAGES = [
        ['name' => 'Rudi Hartono', 'email' => 'rudi@contoh.test', 'phone' => null, 'subject' => 'Pertanyaan jam operasional', 'message' => 'Selamat pagi. Mohon informasi jam operasional poliklinik pada hari Sabtu dan hari libur. Terima kasih.', 'status' => 'new', 'handled' => false],
        ['name' => 'Sinta Maharani', 'email' => 'sinta@contoh.test', 'phone' => '081234567890', 'subject' => 'Menanyakan ketersediaan kamar VIP', 'message' => 'Saya ingin menanyakan apakah kamar VIP masih tersedia untuk beberapa hari ke depan. Mohon informasinya.', 'status' => 'new', 'handled' => false],
        ['name' => 'Tono Wibisono', 'email' => 'tono@contoh.test', 'phone' => null, 'subject' => 'Saran tampilan jadwal dokter', 'message' => 'Tampilan jadwal dokter sudah cukup jelas. Saran saya, tambahkan keterangan hari libur pada setiap minggu.', 'status' => 'read', 'handled' => false],
        ['name' => 'Wati Kusuma', 'email' => 'wati@contoh.test', 'phone' => null, 'subject' => 'Perubahan data profil', 'message' => 'Saya ingin mengubah data profil pada akun saya. Mohon petunjuk langkah yang perlu dilakukan.', 'status' => 'replied', 'handled' => true],
        ['name' => 'Vina Oktaviana', 'email' => 'vina@contoh.test', 'phone' => null, 'subject' => 'Pesan uji (diarsipkan)', 'message' => 'Ini adalah pesan uji yang sudah diarsipkan oleh petugas. Tidak ada tindak lanjut yang diperlukan.', 'status' => 'archived', 'handled' => true],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('ContactMessageSeeder DILARANG di APP_ENV='.app()->environment().'. Pesan contoh memuat data pribadi fiktif dan hanya untuk data demo.');
        }

        $handler = User::query()->where('email', self::HANDLER_EMAIL)->first();

        if ($handler === null) {
            throw new RuntimeException('Akun Petugas Pendaftaran Demo belum ada. Jalankan DemoUserSeeder lebih dulu.');
        }

        $ipHash = hash('sha256', '127.0.0.1'.config('app.key'));

        foreach (self::MESSAGES as $index => $row) {
            if (ContactMessage::query()->where('email', $row['email'])->where('subject', $row['subject'])->exists()) {
                $this->command?->warn("Pesan {$row['email']} ({$row['subject']}) sudah ada, dilewati.");

                continue;
            }

            $receivedAt = now()->subDays(6 - ($index + 1));

            $message = new ContactMessage;
            $message->forceFill([
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'subject' => $row['subject'],
                'message' => $row['message'],
                'status' => ContactMessageStatus::from($row['status']),
                'consent_at' => $receivedAt,
                'ip_hash' => $ipHash,
                'handled_by' => $row['handled'] ? $handler->id : null,
                'handled_at' => $row['handled'] ? $receivedAt->copy()->addHour() : null,
                'created_at' => $receivedAt,
                'updated_at' => $row['handled'] ? $receivedAt->copy()->addHour() : $receivedAt,
            ])->save();
        }
    }
}
