<?php

namespace Tests\Feature\View;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\ArticleStatus;
use App\Enums\Availability;
use App\Enums\BookingChannel;
use App\Enums\ContactMessageStatus;
use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Enums\PaymentType;
use App\Enums\SessionResolution;
use App\Enums\SessionStatus;
use App\View\Components\StatusBadge;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures\HostileStatus;
use Tests\Support\Fixtures\PlainEnum;
use Tests\TestCase;

class StatusBadgeComponentTest extends TestCase
{
    /** @return array<string, array{AppointmentStatus, string, string, string}> */
    public static function appointmentBadges(): array
    {
        return [
            'pending' => [AppointmentStatus::Pending, 'warning', 'bi-hourglass-split', 'Menunggu konfirmasi'],
            'confirmed' => [AppointmentStatus::Confirmed, 'primary', 'bi-check-circle', 'Dikonfirmasi'],
            'checked_in' => [AppointmentStatus::CheckedIn, 'info', 'bi-person-check', 'Hadir/check-in'],
            'completed' => [AppointmentStatus::Completed, 'success', 'bi-check2-all', 'Selesai'],
            'cancelled' => [AppointmentStatus::Cancelled, 'secondary', 'bi-x-circle', 'Dibatalkan'],
            'no_show' => [AppointmentStatus::NoShow, 'danger', 'bi-person-x', 'Tidak hadir'],
        ];
    }

    #[DataProvider('appointmentBadges')]
    public function test_appointment_status_badge_shows_color_icon_and_text(AppointmentStatus $status, string $color, string $icon, string $label): void
    {
        $this->blade('<x-status-badge :status="$status" />', ['status' => $status])
            ->assertSee('<span class="badge text-bg-'.$color.'">', false)
            ->assertSee('<i class="bi '.$icon.'" aria-hidden="true"></i> '.$label.'</span>', false);
    }

    public function test_every_badge_enum_case_renders_its_own_label_color_and_icon(): void
    {
        $enums = [
            AppointmentStatus::class, PaymentType::class, BookingChannel::class, SessionStatus::class,
            ExceptionType::class, DoctorStatus::class, ArticleStatus::class, ActorType::class,
            ContactMessageStatus::class, SessionResolution::class, Availability::class,
        ];

        foreach ($enums as $enum) {
            foreach ($enum::cases() as $case) {
                $this->blade('<x-status-badge :status="$status" />', ['status' => $case])
                    ->assertSee('text-bg-'.$case->color(), false)
                    ->assertSee('<i class="bi '.$case->icon().'" aria-hidden="true"></i>', false)
                    ->assertSee($case->label());
            }
        }
    }

    public function test_extra_attributes_are_merged_after_the_badge_classes(): void
    {
        $this->blade('<x-status-badge :status="$status" class="ms-2" id="status-1" />', ['status' => DoctorStatus::Active])
            ->assertSee('class="badge text-bg-success ms-2"', false)
            ->assertSee('id="status-1"', false);
    }

    public function test_labels_are_escaped(): void
    {
        $this->blade('<x-status-badge :status="$status" />', ['status' => HostileStatus::Bad])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_enum_without_label_color_and_icon_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('label()');

        $this->component(StatusBadge::class, ['status' => PlainEnum::Plain]);
    }
}
