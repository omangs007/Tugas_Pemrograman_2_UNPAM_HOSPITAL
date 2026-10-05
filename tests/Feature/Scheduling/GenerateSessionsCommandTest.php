<?php

namespace Tests\Feature\Scheduling;

use App\Models\PracticeSession;
use App\Services\Scheduling\PracticeSessionGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Perintah `sessions:generate {--days=}` dan penjadwalnya (00 F.2 pemicu c, F3-R8).
 * Waktu tes: Senin 5 Oktober 2026 pukul 08.00.
 */
class GenerateSessionsCommandTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 08:00:00');
        $this->seed(SettingsSeeder::class);
    }

    protected function tearDown(): void
    {
        Sleep::fake(false);

        parent::tearDown();
    }

    public function test_it_generates_for_the_default_window_and_reports_the_count_and_range(): void
    {
        $this->createSchedule(['day_of_week' => 1]);

        $this->artisan('sessions:generate')
            ->expectsOutputToContain('5 sesi baru dibangkitkan untuk 05/10/2026 s.d. 04/11/2026.')
            ->assertExitCode(0);

        $this->assertSame(5, PracticeSession::query()->count());
    }

    public function test_the_days_option_overrides_the_window(): void
    {
        $this->createSchedule(['day_of_week' => 1]);

        $this->artisan('sessions:generate', ['--days' => 7])
            ->expectsOutputToContain('2 sesi baru dibangkitkan untuk 05/10/2026 s.d. 12/10/2026.')
            ->assertExitCode(0);

        $this->artisan('sessions:generate', ['--days' => 120])
            ->expectsOutputToContain('s.d. 02/02/2027.')
            ->assertExitCode(0);
    }

    public function test_a_second_run_is_idempotent(): void
    {
        $this->createSchedule(['day_of_week' => 1]);

        $this->artisan('sessions:generate')->assertExitCode(0);

        $this->artisan('sessions:generate')
            ->expectsOutputToContain('0 sesi baru dibangkitkan untuk 05/10/2026 s.d. 04/11/2026.')
            ->assertExitCode(0);

        $this->assertSame(5, PracticeSession::query()->count());
    }

    public function test_the_boundaries_one_and_one_hundred_twenty_are_accepted(): void
    {
        $this->artisan('sessions:generate', ['--days' => 1])->expectsOutputToContain('s.d. 06/10/2026.')->assertExitCode(0);
        $this->artisan('sessions:generate', ['--days' => '120'])->assertExitCode(0);
    }

    /**
     * @return array<string, array{string|int}>
     */
    public static function invalidDays(): array
    {
        return [
            'nol' => ['0'],
            'negatif' => ['-5'],
            '121' => ['121'],
            'huruf' => ['abc'],
            'desimal' => ['3.5'],
            'kosong' => [''],
            'campuran' => ['7a'],
        ];
    }

    #[DataProvider('invalidDays')]
    public function test_an_invalid_days_option_is_rejected_with_exit_code_one_and_generates_nothing(string $value): void
    {
        $this->createSchedule(['day_of_week' => 1]);

        $this->artisan('sessions:generate', ['--days' => $value])
            ->expectsOutputToContain('Opsi --days harus bilangan bulat 1–120.')
            ->assertExitCode(1);

        $this->assertSame(0, PracticeSession::query()->count());
    }

    public function test_it_waits_for_the_lock_and_exits_with_code_one_when_another_process_keeps_it(): void
    {
        $this->createSchedule(['day_of_week' => 1]);
        $other = Cache::lock(PracticeSessionGenerator::LOCK_KEY, 30);
        $this->assertTrue($other->get());

        // Tidur dipalsukan dan menggeser jam tes, sehingga menunggu 10 detik tidak memakan waktu nyata.
        Sleep::fake(syncWithCarbon: true);

        $this->artisan('sessions:generate')
            ->expectsOutputToContain('Pembangkitan sesi sedang berjalan di proses lain. Coba lagi beberapa saat lagi.')
            ->assertExitCode(1);

        $this->assertSame(0, PracticeSession::query()->count());
        $this->assertTrue(now()->greaterThanOrEqualTo(CarbonImmutable::parse('2026-10-05 08:00:09')), 'Perintah menunggu hampir 10 detik (waktu virtual).');
    }

    public function test_it_succeeds_once_the_lock_is_free_and_releases_it_afterwards(): void
    {
        $this->createSchedule(['day_of_week' => 1]);
        $other = Cache::lock(PracticeSessionGenerator::LOCK_KEY, 30);
        $other->get();
        $other->release();

        $this->artisan('sessions:generate')->assertExitCode(0);

        $lock = Cache::lock(PracticeSessionGenerator::LOCK_KEY, 30);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_the_scheduler_runs_the_command_daily_at_five_past_midnight_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'sessions:generate'));

        $this->assertCount(1, $events);

        $event = $events->first();
        $this->assertSame('5 0 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
