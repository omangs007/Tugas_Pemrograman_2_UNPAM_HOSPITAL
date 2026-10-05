<?php

namespace Tests\Unit\Scheduling;

use App\Services\Scheduling\ScheduleChangeReport;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ScheduleChangeReportTest extends TestCase
{
    public function test_report_is_a_final_readonly_value_object_with_zero_defaults(): void
    {
        $class = new ReflectionClass(ScheduleChangeReport::class);
        $report = new ScheduleChangeReport;

        $this->assertTrue($class->isFinal());
        $this->assertTrue($class->isReadOnly());
        $this->assertSame([0, 0, 0, 0, 0, 0], [
            $report->regenerated, $report->updated, $report->quotaAdjusted, $report->needsReview, $report->cancelled, $report->reopened,
        ]);
    }

    public function test_message_is_empty_when_everything_is_zero(): void
    {
        $this->assertSame('', (new ScheduleChangeReport)->message());
    }

    public function test_message_lists_only_the_non_zero_parts_and_ends_with_a_period(): void
    {
        $this->assertSame('3 sesi diperbarui.', (new ScheduleChangeReport(updated: 3))->message());
        $this->assertSame(
            '3 sesi diperbarui, 2 sesi perlu ditindaklanjuti petugas pendaftaran.',
            (new ScheduleChangeReport(updated: 3, needsReview: 2))->message(),
        );
        $this->assertSame('1 sesi dibuka kembali.', (new ScheduleChangeReport(reopened: 1))->message());
    }

    public function test_message_keeps_the_fixed_order_of_f3_r23(): void
    {
        $report = new ScheduleChangeReport(
            regenerated: 4,
            updated: 3,
            quotaAdjusted: 2,
            needsReview: 7,
            cancelled: 5,
            reopened: 6,
        );

        $this->assertSame(
            '4 sesi dibangkitkan, 3 sesi diperbarui, 2 sesi disesuaikan kuotanya, 5 sesi dibatalkan, 6 sesi dibuka kembali, 7 sesi perlu ditindaklanjuti petugas pendaftaran.',
            $report->message(),
        );
    }
}
