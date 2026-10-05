<?php

namespace Tests\Support;

use PDO;
use RuntimeException;

/**
 * Koneksi PDO mentah ke database uji, terpisah dari koneksi aplikasi, untuk tes konkurensi (KON-01).
 * Setiap pemanggilan open() menghasilkan koneksi MariaDB baru (sesi server baru, transaksi sendiri).
 * Parameter diambil dari `config('database.connections.mariadb')`, yaitu nilai yang sama dengan koneksi
 * aplikasi di tes (phpunit.xml memaksa database `unpam_hospital_test`).
 */
final class RawPdoConnection
{
    /**
     * @param  int  $lockWaitTimeoutSeconds  `innodb_lock_wait_timeout` untuk sesi ini; kecil agar tes tidak
     *                                       menunggu 50 detik bila terjadi kekeliruan.
     */
    public static function open(int $lockWaitTimeoutSeconds = 1): PDO
    {
        /** @var array<string, mixed> $config */
        $config = config('database.connections.mariadb');

        $database = (string) $config['database'];
        DatabaseGuard::assertIsTestDatabase($database);

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $database,
        );

        $pdo = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        $pdo->exec("SET time_zone = '".(string) ($config['timezone'] ?? '+07:00')."'");
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = '.max(1, $lockWaitTimeoutSeconds));

        $actual = (int) $pdo->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();

        if ($actual !== max(1, $lockWaitTimeoutSeconds)) {
            throw new RuntimeException("innodb_lock_wait_timeout sesi tidak dapat diatur (nilai: {$actual}).");
        }

        return $pdo;
    }
}
