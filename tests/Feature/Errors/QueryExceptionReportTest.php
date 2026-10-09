<?php

namespace Tests\Feature\Errors;

use App\Exceptions\BookingException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PDOException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * F7A-R25 / Review Focus E.4: callback `report` untuk `QueryException` dan `DeadlockException` di bootstrap/app.php, diuji dengan
 * handler ASLI (bukan Exceptions::fake(), yang melewati callback). Log nyata dialihkan ke saluran `null`; setiap tulisan log
 * ditangkap lewat MessageLogged. Bukti: bindings (keluhan, BPJS) TIDAK ada di log, SQL dengan `?` ADA, dan respons ke
 * pengguna tetap 500 generik.
 */
class QueryExceptionReportTest extends TestCase
{
    private const COMPLAINT = 'KELUHAN-RAHASIA';

    private const BPJS = '0001234567890';

    private const SQL = 'insert into `appointments` (`complaint`, `payment_reference`) values (?, ?)';

    private const EXPECTED = 'Galat basis data: koneksi=mariadb sqlstate=23000 driver=1062 sql='.self::SQL;

    /** @var list<MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false, 'logging.default' => 'null']);

        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->logged[] = $event;
        });

        Route::middleware('web')->get('/_uji/query-gagal', fn () => throw $this->queryFault());
        Route::middleware('web')->post('/_uji/query-gagal-form', fn () => throw $this->queryFault());
        Route::middleware('web')->get('/_uji/unik-gagal', fn () => throw $this->uniqueFault());
        Route::middleware('web')->get('/_uji/deadlock-gagal', fn () => throw $this->deadlockFault());
        Route::middleware('web')->get('/_uji/galat-biasa', fn () => throw new RuntimeException('galat biasa'));
    }

    private function driverError(int $errno = 1062, string $state = '23000'): PDOException
    {
        $driverMessage = "Duplicate entry '".self::BPJS."' for key 'appointments_x'";
        $error = new PDOException("SQLSTATE[{$state}]: Integrity constraint violation: {$errno} {$driverMessage}");
        $error->errorInfo = [$state, $errno, $driverMessage];

        return $error;
    }

    private function queryFault(): QueryException
    {
        return new QueryException('mariadb', self::SQL, [self::COMPLAINT, self::BPJS], $this->driverError());
    }

    private function uniqueFault(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException('mariadb', self::SQL, [self::COMPLAINT, self::BPJS], $this->driverError());
    }

    private function deadlockFault(): DeadlockException
    {
        $original = new QueryException('mariadb', self::SQL, [self::COMPLAINT, self::BPJS], $this->driverError());

        return new DeadlockException($original->getMessage(), 0, $original);
    }

    /** @return list<MessageLogged> */
    private function errorRecords(): array
    {
        return array_values(array_filter($this->logged, fn (MessageLogged $record): bool => $record->level === 'error'));
    }

    /** Seluruh isi semua catatan log (pesan + konteks, termasuk pesan pengecualian di konteks) untuk pencarian rahasia. */
    private function everythingLogged(): string
    {
        $blob = '';

        foreach ($this->logged as $record) {
            $blob .= $record->message."\n";

            foreach ($record->context as $value) {
                $blob .= ($value instanceof Throwable ? $value->getMessage() : json_encode($value))."\n";
            }
        }

        return $blob;
    }

    private function assertNoSecretInTheLog(): void
    {
        $blob = $this->everythingLogged();

        foreach ([self::COMPLAINT, self::BPJS, 'Duplicate entry', 'appointments_x'] as $secret) {
            $this->assertStringNotContainsString($secret, $blob, "Bocor ke log: {$secret}");
        }
    }

    public function test_a_query_exception_is_logged_with_the_sql_placeholders_and_without_bindings(): void
    {
        $this->get('/_uji/query-gagal')->assertStatus(500);

        $errors = $this->errorRecords();
        $this->assertCount(1, $errors, 'Tepat satu catatan galat (pelaporan bawaan dihentikan).');
        $this->assertSame(self::EXPECTED, $errors[0]->message);
        $this->assertStringContainsString('values (?, ?)', $errors[0]->message);
        $this->assertArrayNotHasKey('exception', $errors[0]->context, 'Pengecualian (yang pesannya memuat bindings) tidak ikut konteks.');
        $this->assertNoSecretInTheLog();
    }

    public function test_the_user_still_gets_the_generic_500_without_any_internal_detail(): void
    {
        $this->get('/_uji/query-gagal')
            ->assertStatus(500)
            ->assertSee('Terjadi kesalahan pada server')
            ->assertSee('Kami mencatat masalah ini.')
            ->assertDontSee(self::COMPLAINT)
            ->assertDontSee(self::BPJS)
            ->assertDontSee('insert into')
            ->assertDontSee('Duplicate entry')
            ->assertDontSee('QueryException')
            ->assertDontSee('Whoops');
    }

    public function test_a_json_client_also_gets_a_generic_500(): void
    {
        $response = $this->getJson('/_uji/query-gagal')->assertStatus(500);

        $this->assertStringNotContainsString(self::COMPLAINT, $response->getContent());
        $this->assertStringNotContainsString('insert into', $response->getContent());
        $this->assertCount(1, $this->errorRecords());
    }

    public function test_a_failing_form_post_logs_no_submitted_value(): void
    {
        $this->post('/_uji/query-gagal-form', ['complaint' => self::COMPLAINT, 'bpjs_number' => self::BPJS])
            ->assertStatus(500)
            ->assertDontSee(self::COMPLAINT);

        $this->assertNoSecretInTheLog();
        $this->assertCount(1, $this->errorRecords());
    }

    public function test_a_unique_constraint_violation_is_sanitised_as_well(): void
    {
        $this->get('/_uji/unik-gagal')->assertStatus(500);

        $errors = $this->errorRecords();
        $this->assertCount(1, $errors);
        $this->assertSame(self::EXPECTED, $errors[0]->message);
        $this->assertNoSecretInTheLog();
    }

    public function test_a_deadlock_exception_is_logged_from_its_query_exception_without_bindings(): void
    {
        // Prasyarat: pesan DeadlockException menyalin pesan asli yang memuat bindings.
        $this->assertStringContainsString(self::COMPLAINT, $this->deadlockFault()->getMessage());

        $this->get('/_uji/deadlock-gagal')->assertStatus(500)->assertDontSee(self::COMPLAINT);

        $errors = $this->errorRecords();
        $this->assertCount(1, $errors);
        $this->assertSame(self::EXPECTED, $errors[0]->message);
        $this->assertArrayNotHasKey('exception', $errors[0]->context);
        $this->assertNoSecretInTheLog();
    }

    public function test_the_report_helper_is_sanitised_too(): void
    {
        // Jalur non-HTTP (mis. `catch (Throwable $e) { report($e); }` pada notifier atau perintah konsol).
        report($this->queryFault());
        report($this->deadlockFault());

        $errors = $this->errorRecords();
        $this->assertCount(2, $errors);
        $this->assertSame(self::EXPECTED, $errors[0]->message);
        $this->assertSame(self::EXPECTED, $errors[1]->message);
        $this->assertNoSecretInTheLog();
    }

    public function test_other_exceptions_are_still_reported_by_default_with_their_own_message_and_context(): void
    {
        $this->get('/_uji/galat-biasa')->assertStatus(500);

        $errors = $this->errorRecords();
        $this->assertCount(1, $errors);
        $this->assertSame('galat biasa', $errors[0]->message);
        $this->assertInstanceOf(RuntimeException::class, $errors[0]->context['exception']);
    }

    public function test_booking_exceptions_remain_unreported(): void
    {
        report(BookingException::make(BookingException::QUOTA_FULL));

        $this->assertSame([], $this->errorRecords());
    }
}
