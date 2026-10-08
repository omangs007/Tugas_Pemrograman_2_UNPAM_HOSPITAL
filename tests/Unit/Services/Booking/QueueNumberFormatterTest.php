<?php

namespace Tests\Unit\Services\Booking;

use App\Services\Booking\QueueNumberFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * F6-R6: tampilan nomor antrean = kode poliklinik + "-" + nomor tiga digit (BKG-17). Murni, tanpa DB.
 */
class QueueNumberFormatterTest extends TestCase
{
    /** @return array<string, array{0: string, 1: int, 2: string}> */
    public static function formatCases(): array
    {
        return [
            'antrean 1' => ['ANK', 1, 'ANK-001'],
            'antrean 7' => ['ANK', 7, 'ANK-007'],
            'antrean 10' => ['ANK', 10, 'ANK-010'],
            'antrean 99' => ['ANK', 99, 'ANK-099'],
            'antrean 100' => ['ANK', 100, 'ANK-100'],
            'antrean 999' => ['ANK', 999, 'ANK-999'],
            'antrean 1000 melebar menjadi empat digit' => ['ANK', 1000, 'ANK-1000'],
            'antrean 12345' => ['ANK', 12345, 'ANK-12345'],
            'kode dua huruf' => ['PD', 3, 'PD-003'],
            'kode lima huruf' => ['BEDAH', 42, 'BEDAH-042'],
        ];
    }

    #[DataProvider('formatCases')]
    public function test_format_pads_the_queue_number_to_three_digits(string $clinicCode, int $queueNumber, string $expected): void
    {
        $this->assertSame($expected, QueueNumberFormatter::format($clinicCode, $queueNumber));
    }
}
