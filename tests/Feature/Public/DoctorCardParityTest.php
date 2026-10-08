<?php

namespace Tests\Feature\Public;

use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Services\Scheduling\PracticeSessionGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Carry E12 (F6-R32): beranda (`/`) dan direktori dokter (`/dokter`) memakai definisi "jadwal terdekat" dan saringan
 * spesialisasi aktif yang sama. Tes karakterisasi: lulus sebelum dan sesudah penyatuan ke
 * `DoctorAvailabilityQuery::prepareCards()`. Sekarang = Senin 2026-10-05 07.00.
 */
class DoctorCardParityTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-05 07:00:00'); // Senin

        $today = CarbonImmutable::today();
        Cache::put(PracticeSessionGenerator::markerKey($today), true, $today->endOfDay());
    }

    /** @param  array<string, mixed>  $overrides */
    private function sessionOf(Doctor $doctor, string $date, array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ], $overrides));
    }

    private function leaveSession(Doctor $doctor, string $date): PracticeSession
    {
        $exception = $this->createException(['doctor_id' => $doctor->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => $date, 'end_date' => $date]);

        return $this->sessionOf($doctor, $date, ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $exception->id]);
    }

    /**
     * Fakta kartu satu dokter dari HTML halaman: badge spesialisasi, "Jadwal terdekat", `data-availability`, dan tautan
     * Daftar. Kartu = `<article class="… doctor-card">` yang memuat nama dokter.
     *
     * @return array{specialties: list<string>, next: list<string>, availability: ?string, register: ?string}
     */
    private function cardFacts(string $html, string $doctorName): array
    {
        preg_match_all('#<article[^>]*doctor-card[^>]*>.*?</article>#s', $html, $cards);
        $card = collect($cards[0])->first(fn (string $item): bool => str_contains($item, $doctorName));

        $this->assertNotNull($card, "Kartu {$doctorName} tidak ditemukan.");

        preg_match_all('#<span class="badge text-bg-light border">([^<]*)</span>#', $card, $specialties);
        preg_match('#Jadwal terdekat: <time datetime="([^"]*)">([^<]*)</time> · ([^<]*)</p>#u', $card, $next);
        preg_match('#data-availability="([^"]*)"#', $card, $availability);
        preg_match('#<a class="btn btn-primary btn-sm[^"]*" href="([^"]*)"#', $card, $register);

        return [
            'specialties' => $specialties[1],
            'next' => array_slice($next, 1),
            'availability' => $availability[1] ?? null,
            'register' => $register[1] ?? null,
        ];
    }

    public function test_home_and_directory_show_the_same_nearest_session_and_active_specialty_for_a_doctor(): void
    {
        $hidden = $this->createSpecialty(['name' => 'Spesialis Nonaktif Utama', 'is_active' => false]);
        $shown = $this->createSpecialty(['name' => 'Spesialis Aktif Tambahan']);
        $rani = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null], null, [$hidden, $shown]);
        $this->createDoctor(['name' => 'Teguh Wicaksono', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->leaveSession($rani, '2026-10-06');
        $this->sessionOf($rani, '2026-10-08', ['start_time' => '13:00:00', 'end_time' => '15:00:00']);

        $home = $this->cardFacts($this->get('/')->assertOk()->getContent(), 'dr. Rani Oktaviani');
        $directory = $this->cardFacts($this->get('/dokter')->assertOk()->getContent(), 'dr. Rani Oktaviani');

        $this->assertSame($home, $directory);
        $this->assertSame(['Spesialis Aktif Tambahan'], $home['specialties']);
        $this->assertSame(['2026-10-08', 'Kamis, 8 Oktober 2026', '13.00–15.00'], $home['next']);
        $this->assertSame('available', $home['availability']);
    }

    public function test_a_doctor_with_only_a_leave_session_has_no_nearest_session_on_either_page(): void
    {
        $teguh = $this->createDoctor(['name' => 'Teguh Wicaksono', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->leaveSession($teguh, '2026-10-06');

        $home = $this->cardFacts($this->get('/')->assertOk()->getContent(), 'dr. Teguh Wicaksono');
        $directory = $this->cardFacts($this->get('/dokter')->assertOk()->getContent(), 'dr. Teguh Wicaksono');

        $this->assertSame($home, $directory);
        $this->assertSame([], $home['next']);
        $this->assertNull($home['availability']);
        $this->assertNull($home['register']);
    }

    /** Hanya #6 dengan filter `tanggal` menampilkan Cuti sebagai sesi yang diwakili kartu (F3-R25); beranda tidak punya filter. */
    public function test_the_date_filter_alone_lets_a_leave_session_represent_the_card(): void
    {
        $teguh = $this->createDoctor(['name' => 'Teguh Wicaksono', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->leaveSession($teguh, '2026-10-06');
        $this->sessionOf($teguh, '2026-10-08');

        $filtered = $this->cardFacts($this->get('/dokter?tanggal=2026-10-06')->assertOk()->getContent(), 'dr. Teguh Wicaksono');
        $unfiltered = $this->cardFacts($this->get('/dokter')->assertOk()->getContent(), 'dr. Teguh Wicaksono');
        $home = $this->cardFacts($this->get('/')->assertOk()->getContent(), 'dr. Teguh Wicaksono');

        $this->assertSame('leave', $filtered['availability']);
        $this->assertSame('2026-10-06', $filtered['next'][0]);
        $this->assertNull($filtered['register']);
        $this->assertSame('2026-10-08', $unfiltered['next'][0]);
        $this->assertSame($unfiltered, $home);
    }
}
