<?php

namespace Tests\Unit\Support;

use App\Support\QueryLogSanitizer;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * F7A-R25: isi pesan log galat basis data = koneksi, SQLSTATE, kode driver, dan SQL TANPA bindings (maks 1000 karakter).
 * Keluhan, alamat, dan nomor BPJS (bindings) serta pesan driver (`Duplicate entry '…'`) tidak pernah ikut.
 */
class QueryLogSanitizerTest extends TestCase
{
    private const SQL = 'insert into `appointments` (`complaint`, `payment_reference`) values (?, ?)';

    private const COMPLAINT = 'KELUHAN-RAHASIA';

    private const BPJS = '0001234567890';

    private function driverError(int $errno = 1062, string $state = '23000'): PDOException
    {
        $driverMessage = "Duplicate entry '".self::BPJS."' for key 'appointments_x'";
        $error = new PDOException("SQLSTATE[{$state}]: Integrity constraint violation: {$errno} {$driverMessage}");
        $error->errorInfo = [$state, $errno, $driverMessage];

        return $error;
    }

    private function query(string $sql = self::SQL, ?PDOException $driver = null): QueryException
    {
        return new QueryException('mariadb', $sql, [self::COMPLAINT, self::BPJS], $driver ?? $this->driverError());
    }

    private function sqlPart(string $message): string
    {
        return substr($message, (int) strpos($message, 'sql=') + 4);
    }

    public function test_the_message_has_connection_sqlstate_driver_code_and_the_sql_with_placeholders(): void
    {
        $message = QueryLogSanitizer::message($this->query());

        $this->assertSame('Galat basis data: koneksi=mariadb sqlstate=23000 driver=1062 sql='.self::SQL, $message);
    }

    public function test_bindings_and_the_driver_message_never_appear(): void
    {
        $exception = $this->query();

        // Prasyarat: pesan bawaan Laravel memang membocorkan bindings dan pesan driver.
        $this->assertStringContainsString(self::COMPLAINT, $exception->getMessage());
        $this->assertStringContainsString(self::BPJS, $exception->getMessage());

        $message = QueryLogSanitizer::message($exception);

        foreach ([self::COMPLAINT, self::BPJS, 'Duplicate entry', 'Integrity constraint violation', 'appointments_x'] as $secret) {
            $this->assertStringNotContainsString($secret, $message, "Bocor ke log: {$secret}");
        }
    }

    public function test_the_raw_sql_with_embedded_bindings_is_not_used(): void
    {
        $message = QueryLogSanitizer::message($this->query());

        $this->assertStringContainsString('values (?, ?)', $message);
        $this->assertStringNotContainsString('values ('.self::COMPLAINT, $message);
    }

    public function test_unknown_parts_are_a_dash(): void
    {
        $message = QueryLogSanitizer::message(new QueryException('mariadb', 'select 1', [], new RuntimeException('galat')));

        $this->assertSame('Galat basis data: koneksi=mariadb sqlstate=- driver=- sql=select 1', $message);
    }

    public function test_a_unique_constraint_violation_is_a_query_exception_too(): void
    {
        $exception = new UniqueConstraintViolationException('mariadb', self::SQL, [self::COMPLAINT, self::BPJS], $this->driverError());

        $this->assertSame('Galat basis data: koneksi=mariadb sqlstate=23000 driver=1062 sql='.self::SQL, QueryLogSanitizer::message($exception));
    }

    public function test_the_sql_is_cut_at_one_thousand_characters(): void
    {
        $message = QueryLogSanitizer::message($this->query('select '.str_repeat('x', 1500)));

        $this->assertSame(1000, mb_strlen($this->sqlPart($message)));
        $this->assertStringStartsWith('select xxx', $this->sqlPart($message));
    }

    public function test_the_cut_counts_characters_not_bytes(): void
    {
        $message = QueryLogSanitizer::message($this->query(str_repeat("\u{00E9}", 1500)));

        $this->assertSame(1000, mb_strlen($this->sqlPart($message)));
        $this->assertTrue(mb_check_encoding($message, 'UTF-8'));
    }

    public function test_a_short_sql_is_kept_whole(): void
    {
        $this->assertSame('select 1', $this->sqlPart(QueryLogSanitizer::message($this->query('select 1'))));
    }

    public function test_a_deadlock_exception_is_described_from_the_query_exception_in_its_chain(): void
    {
        $original = $this->query(self::SQL, $this->driverError(1213, '40001'));
        $deadlock = new DeadlockException($original->getMessage(), 0, $original);

        // Prasyarat: DeadlockException menyalin pesan galat asli yang berisi bindings.
        $this->assertStringContainsString(self::COMPLAINT, $deadlock->getMessage());

        $message = QueryLogSanitizer::message($deadlock);

        $this->assertSame('Galat basis data: koneksi=mariadb sqlstate=40001 driver=1213 sql='.self::SQL, $message);
        $this->assertStringNotContainsString(self::COMPLAINT, $message);
        $this->assertStringNotContainsString(self::BPJS, $message);
    }

    public function test_a_nested_deadlock_chain_is_followed_to_the_query_exception(): void
    {
        $original = $this->query(self::SQL, $this->driverError(1213, '40001'));
        $inner = new DeadlockException($original->getMessage(), 0, $original);
        $outer = new DeadlockException($inner->getMessage(), 0, $inner);

        $this->assertSame('Galat basis data: koneksi=mariadb sqlstate=40001 driver=1213 sql='.self::SQL, QueryLogSanitizer::message($outer));
    }

    public function test_a_deadlock_exception_without_a_query_exception_reveals_nothing(): void
    {
        $message = QueryLogSanitizer::message(new DeadlockException('SQLSTATE[40001]: ... 1213 Deadlock found ... (SQL: select '.self::COMPLAINT.')'));

        $this->assertSame('Galat basis data: koneksi=- sqlstate=- driver=- sql=-', $message);
    }
}
