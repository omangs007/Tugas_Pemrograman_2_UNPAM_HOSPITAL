<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Pemicu (c) pembangkitan sesi (00 F.2, 11): `php artisan sessions:generate {--days=}`. Idempoten. Memakai
 * kunci yang sama dengan pembangkitan harian halaman publik, tetapi menunggu hingga 10 detik (F3-R8).
 * Tidak menulis audit per sesi (12 §8), sehingga tidak memerlukan pelaku (Actor).
 */
class GenerateSessions extends Command
{
    protected $signature = 'sessions:generate {--days= : Jumlah hari ke depan (1–120; bawaan booking.window_days)}';

    protected $description = 'Bangkitkan sesi praktik dari templat jadwal untuk hari ini sampai beberapa hari ke depan';

    public function handle(PracticeSessionGenerator $generator, SettingsService $settings): int
    {
        $days = $this->resolveDays($settings);

        if ($days === null) {
            $this->error('Opsi --days harus bilangan bulat 1–120.');

            return self::FAILURE;
        }

        $from = CarbonImmutable::today();
        $to = $from->addDays($days);

        try {
            $created = Cache::lock(PracticeSessionGenerator::LOCK_KEY, 30)
                ->block(10, fn (): int => $generator->ensureRange($from, $to));
        } catch (LockTimeoutException) {
            $this->error('Pembangkitan sesi sedang berjalan di proses lain. Coba lagi beberapa saat lagi.');

            return self::FAILURE;
        }

        $this->info("{$created} sesi baru dibangkitkan untuk {$from->format('d/m/Y')} s.d. {$to->format('d/m/Y')}.");

        return self::SUCCESS;
    }

    /** Jumlah hari dari opsi, atau `booking.window_days` bila opsi tidak diberikan; NULL bila tidak valid. */
    private function resolveDays(SettingsService $settings): ?int
    {
        $option = $this->option('days');

        if ($option === null) {
            return max(1, $settings->int('booking.window_days', 30));
        }

        $days = filter_var($option, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);

        return $days === false ? null : $days;
    }
}
