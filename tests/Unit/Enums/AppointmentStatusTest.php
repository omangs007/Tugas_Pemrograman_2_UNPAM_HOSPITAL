<?php

namespace Tests\Unit\Enums;

use App\Enums\AppointmentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AppointmentStatusTest extends TestCase
{
    /** @return array<string, array{0: AppointmentStatus, 1: bool, 2: bool, 3: bool}> */
    public static function classification(): array
    {
        // status, isActive, consumesQuota, isFinal  (00 D / 10 §4.1)
        return [
            'pending' => [AppointmentStatus::Pending, true, true, false],
            'confirmed' => [AppointmentStatus::Confirmed, true, true, false],
            'checked_in' => [AppointmentStatus::CheckedIn, true, true, false],
            'completed' => [AppointmentStatus::Completed, false, true, true],
            'cancelled' => [AppointmentStatus::Cancelled, false, false, true],
            'no_show' => [AppointmentStatus::NoShow, false, true, true],
        ];
    }

    #[DataProvider('classification')]
    public function test_status_classification(AppointmentStatus $status, bool $active, bool $consumesQuota, bool $final): void
    {
        $this->assertSame($active, $status->isActive());
        $this->assertSame($consumesQuota, $status->consumesQuota());
        $this->assertSame($final, $status->isFinal());
    }

    public function test_constant_sets_follow_the_blueprint(): void
    {
        $this->assertSame(
            [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn],
            AppointmentStatus::ACTIVE,
        );
        $this->assertSame(
            [
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::CheckedIn,
                AppointmentStatus::Completed,
                AppointmentStatus::NoShow,
            ],
            AppointmentStatus::QUOTA_CONSUMING,
        );
        $this->assertSame(
            [AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow],
            AppointmentStatus::FINAL,
        );
    }

    public function test_static_helpers_return_the_constant_sets(): void
    {
        $this->assertSame(AppointmentStatus::ACTIVE, AppointmentStatus::active());
        $this->assertSame(AppointmentStatus::QUOTA_CONSUMING, AppointmentStatus::quotaConsuming());
        $this->assertSame(AppointmentStatus::FINAL, AppointmentStatus::finals());
    }

    public function test_quota_consuming_is_every_status_except_cancelled(): void
    {
        $expected = array_values(array_filter(
            AppointmentStatus::cases(),
            static fn (AppointmentStatus $status): bool => $status !== AppointmentStatus::Cancelled,
        ));

        $this->assertSame($expected, AppointmentStatus::QUOTA_CONSUMING);
    }

    public function test_active_and_final_sets_never_overlap_and_cover_every_status(): void
    {
        $this->assertSame([], array_values(array_filter(
            AppointmentStatus::ACTIVE,
            static fn (AppointmentStatus $status): bool => in_array($status, AppointmentStatus::FINAL, true),
        )));

        $this->assertCount(
            count(AppointmentStatus::cases()),
            [...AppointmentStatus::ACTIVE, ...AppointmentStatus::FINAL],
        );
    }
}
