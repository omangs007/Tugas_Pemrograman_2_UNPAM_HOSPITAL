<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use App\Services\Booking\AppointmentStatusService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

/**
 * Pemicu otomatis T10 (00 F.8, F7A-R7): `php artisan appointments:mark-no-show {--date=}`. Menandai tidak hadir
 * appointment `pending`/`confirmed` pada sesi yang sudah berakhir, dengan pelaku sistem. Tanpa `--date` = semua
 * sesi yang sudah berakhir. Idempoten (yang sudah `no_show` tidak diproses lagi). Sesi `needs_review` dilewati.
 * Dijadwalkan harian pukul 23.30 (routes/console.php).
 */
class MarkNoShowAppointments extends Command
{
    protected $signature = 'appointments:mark-no-show {--date= : Tanggal YYYY-MM-DD (bawaan: semua sesi yang sudah berakhir)}';

    protected $description = 'Tandai tidak hadir pendaftaran yang belum check-in pada sesi praktik yang sudah berakhir';

    public function handle(AppointmentStatusService $service): int
    {
        $date = null;
        $option = $this->option('date');

        if ($option !== null) {
            $date = $this->parseDate((string) $option);

            if ($date === null) {
                $this->error('Opsi --date harus berupa tanggal dengan format YYYY-MM-DD.');

                return self::FAILURE;
            }

            if ($date->greaterThan(CarbonImmutable::today())) {
                $this->error('Opsi --date tidak boleh tanggal setelah hari ini.');

                return self::FAILURE;
            }
        }

        $sessions = $service->sessionsAwaitingNoShow($date);

        if ($sessions->isEmpty()) {
            $this->info('Tidak ada pendaftaran yang perlu ditandai tidak hadir.');

            return self::SUCCESS;
        }

        $actor = Actor::system();
        $total = 0;
        $processed = 0;

        foreach ($sessions as $session) {
            try {
                $count = $service->markNoShowForSession($session, $actor);
            } catch (InvalidTransitionException) {
                // Berubah menjadi needs_review (atau belum berakhir) sejak daftar dibaca: dilewati.
                continue;
            } catch (BookingException $e) {
                if ($e->errorCode !== BookingException::SYSTEM_BUSY) {
                    throw $e;
                }

                $this->error("Sistem sedang sibuk; {$total} pendaftaran sudah ditandai sebelum berhenti. Jalankan ulang perintah ini.");

                return self::FAILURE;
            }

            $total += $count;
            $processed++;

            $this->line("- {$session->session_date->format('d/m/Y')} {$session->time_range} {$session->clinic->name}, {$session->doctor->name}: {$count} pendaftaran.");
        }

        $this->info("{$total} pendaftaran pada {$processed} sesi ditandai tidak hadir.");

        return self::SUCCESS;
    }

    /** Tanggal `Y-m-d` yang sah (tanpa limpahan seperti 2026-02-30) pukul 00.00 zona aplikasi, atau NULL. */
    private function parseDate(string $value): ?CarbonImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone((string) config('app.timezone')));

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            return null;
        }

        return CarbonImmutable::instance($parsed);
    }
}
