<?php

namespace Tests\Unit\Enums;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\ArticleStatus;
use App\Enums\Availability;
use App\Enums\BookingChannel;
use App\Enums\ClinicType;
use App\Enums\Concerns\HasOptions;
use App\Enums\ContactMessageStatus;
use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Enums\FacilityCategory;
use App\Enums\Gender;
use App\Enums\PaymentType;
use App\Enums\SessionResolution;
use App\Enums\SessionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;

class EnumCatalogTest extends TestCase
{
    /**
     * Salinan literal 10-struktur-folder.md §4.1-4.3 (dan 00 D).
     * Kolom: enum, nama case, nilai, label, color (null = enum label-saja, tidak punya color()), icon.
     *
     * @var list<array{0: class-string, 1: string, 2: string, 3: string, 4: ?string, 5: string}>
     */
    private const CATALOG = [
        [AppointmentStatus::class, 'Pending', 'pending', 'Menunggu konfirmasi', 'warning', 'bi-hourglass-split'],
        [AppointmentStatus::class, 'Confirmed', 'confirmed', 'Dikonfirmasi', 'primary', 'bi-check-circle'],
        [AppointmentStatus::class, 'CheckedIn', 'checked_in', 'Hadir/check-in', 'info', 'bi-person-check'],
        [AppointmentStatus::class, 'Completed', 'completed', 'Selesai', 'success', 'bi-check2-all'],
        [AppointmentStatus::class, 'Cancelled', 'cancelled', 'Dibatalkan', 'secondary', 'bi-x-circle'],
        [AppointmentStatus::class, 'NoShow', 'no_show', 'Tidak hadir', 'danger', 'bi-person-x'],

        [PaymentType::class, 'Umum', 'umum', 'Umum/Pribadi', 'secondary', 'bi-wallet2'],
        [PaymentType::class, 'Bpjs', 'bpjs', 'BPJS Kesehatan', 'success', 'bi-shield-plus'],
        [PaymentType::class, 'Asuransi', 'asuransi', 'Asuransi', 'primary', 'bi-shield-check'],

        [BookingChannel::class, 'Online', 'online', 'Online', 'info', 'bi-globe2'],
        [BookingChannel::class, 'Loket', 'loket', 'Loket', 'secondary', 'bi-building'],

        [SessionStatus::class, 'Open', 'open', 'Dibuka', 'success', 'bi-door-open'],
        [SessionStatus::class, 'Closed', 'closed', 'Ditutup', 'secondary', 'bi-door-closed'],
        [SessionStatus::class, 'NeedsReview', 'needs_review', 'Perlu tindak lanjut', 'warning', 'bi-exclamation-triangle'],
        [SessionStatus::class, 'Cancelled', 'cancelled', 'Dibatalkan', 'danger', 'bi-x-octagon'],

        [ExceptionType::class, 'DoctorLeave', 'doctor_leave', 'Cuti dokter', 'warning', 'bi-person-dash'],
        [ExceptionType::class, 'HospitalHoliday', 'hospital_holiday', 'Libur rumah sakit', 'danger', 'bi-calendar-x'],
        [ExceptionType::class, 'NationalHoliday', 'national_holiday', 'Libur nasional', 'info', 'bi-flag'],
        [ExceptionType::class, 'Other', 'other', 'Lainnya', 'secondary', 'bi-three-dots'],

        [DoctorStatus::class, 'Active', 'active', 'Aktif', 'success', 'bi-check-circle'],
        [DoctorStatus::class, 'Inactive', 'inactive', 'Nonaktif', 'secondary', 'bi-pause-circle'],
        [DoctorStatus::class, 'Archived', 'archived', 'Diarsipkan', 'dark', 'bi-archive'],

        [ArticleStatus::class, 'Draft', 'draft', 'Draf', 'secondary', 'bi-pencil'],
        [ArticleStatus::class, 'Published', 'published', 'Terbit', 'success', 'bi-globe'],

        [ActorType::class, 'Patient', 'patient', 'Pasien', 'info', 'bi-person'],
        [ActorType::class, 'Staff', 'staff', 'Staf', 'primary', 'bi-person-badge'],
        [ActorType::class, 'System', 'system', 'Sistem', 'secondary', 'bi-gear'],

        [ContactMessageStatus::class, 'New', 'new', 'Baru', 'primary', 'bi-envelope'],
        [ContactMessageStatus::class, 'Read', 'read', 'Dibaca', 'secondary', 'bi-envelope-open'],
        [ContactMessageStatus::class, 'Replied', 'replied', 'Dibalas', 'success', 'bi-reply'],
        [ContactMessageStatus::class, 'Archived', 'archived', 'Diarsipkan', 'dark', 'bi-archive'],

        [SessionResolution::class, 'Keep', 'keep', 'Pertahankan sesi lama', 'secondary', 'bi-arrow-counterclockwise'],
        [SessionResolution::class, 'Apply', 'apply', 'Terapkan perubahan jam', 'primary', 'bi-clock-history'],
        [SessionResolution::class, 'Cancel', 'cancel', 'Batalkan sesi', 'danger', 'bi-x-circle'],

        [Availability::class, 'Available', 'available', 'Tersedia', 'success', 'bi-check-circle'],
        [Availability::class, 'Full', 'full', 'Penuh', 'danger', 'bi-people-fill'],
        [Availability::class, 'BookingClosed', 'booking_closed', 'Pendaftaran online ditutup', 'secondary', 'bi-lock'],
        [Availability::class, 'Closed', 'closed', 'Ditutup', 'secondary', 'bi-slash-circle'],
        [Availability::class, 'Leave', 'leave', 'Cuti', 'warning', 'bi-person-dash'],
        [Availability::class, 'Holiday', 'holiday', 'Libur', 'info', 'bi-calendar-event'],
        [Availability::class, 'Cancelled', 'cancelled', 'Dibatalkan', 'secondary', 'bi-x-circle'],
        [Availability::class, 'UnderReview', 'under_review', 'Sedang diperbarui', 'warning', 'bi-arrow-repeat'],
        [Availability::class, 'Past', 'past', 'Sudah lewat', 'secondary', 'bi-clock-history'],

        [AccountType::class, 'Patient', 'patient', 'Pasien', null, 'bi-person'],
        [AccountType::class, 'Staff', 'staff', 'Staf', null, 'bi-person-badge'],

        [Gender::class, 'Male', 'L', 'Laki-laki', null, 'bi-gender-male'],
        [Gender::class, 'Female', 'P', 'Perempuan', null, 'bi-gender-female'],

        [ClinicType::class, 'Poliklinik', 'poliklinik', 'Poliklinik', null, 'bi-hospital'],
        [ClinicType::class, 'Penunjang', 'penunjang', 'Layanan penunjang', null, 'bi-clipboard2-pulse'],

        [FacilityCategory::class, 'Medis', 'medis', 'Fasilitas medis', null, 'bi-heart-pulse'],
        [FacilityCategory::class, 'Penunjang', 'penunjang', 'Penunjang', null, 'bi-tools'],
        [FacilityCategory::class, 'Umum', 'umum', 'Fasilitas umum', null, 'bi-building'],
    ];

    private static function shortName(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }

    /** @return array<string, array{0: class-string, 1: string, 2: string, 3: string, 4: ?string, 5: string}> */
    public static function catalog(): array
    {
        $rows = [];

        foreach (self::CATALOG as $row) {
            $rows[self::shortName($row[0]).'::'.$row[1]] = $row;
        }

        return $rows;
    }

    /** @return array<string, array{0: class-string}> */
    public static function enums(): array
    {
        $rows = [];

        foreach (array_unique(array_column(self::CATALOG, 0)) as $class) {
            $rows[self::shortName($class)] = [$class];
        }

        return $rows;
    }

    #[DataProvider('catalog')]
    public function test_case_matches_the_blueprint_table(string $enum, string $case, string $value, string $label, ?string $color, string $icon): void
    {
        $this->assertTrue(enum_exists($enum), "Enum {$enum} belum ada.");

        $instance = constant($enum.'::'.$case);

        $this->assertSame($value, $instance->value);
        $this->assertSame($label, $instance->label());
        $this->assertSame($icon, $instance->icon());
        $this->assertSame($instance, $enum::from($value));

        if ($color === null) {
            $this->assertFalse(method_exists($instance, 'color'), "{$enum} adalah enum label-saja dan tidak boleh punya color().");
        } else {
            $this->assertSame($color, $instance->color());
        }
    }

    #[DataProvider('enums')]
    public function test_enum_has_exactly_the_blueprint_cases(string $enum): void
    {
        $expected = array_values(array_map(
            static fn (array $row): string => $row[1],
            array_filter(self::CATALOG, static fn (array $row): bool => $row[0] === $enum),
        ));

        $actual = array_map(static fn ($case): string => $case->name, $enum::cases());

        $this->assertSame($expected, $actual);
    }

    #[DataProvider('enums')]
    public function test_values_and_options_follow_case_order(string $enum): void
    {
        $rows = array_values(array_filter(self::CATALOG, static fn (array $row): bool => $row[0] === $enum));

        $this->assertSame(array_column($rows, 2), $enum::values());
        $this->assertSame(array_combine(array_column($rows, 2), array_column($rows, 3)), $enum::options());
    }

    #[DataProvider('enums')]
    public function test_enum_is_string_backed_and_uses_has_options(string $enum): void
    {
        $this->assertSame('string', (new ReflectionEnum($enum))->getBackingType()?->getName());
        $this->assertContains(HasOptions::class, class_uses($enum));
    }

    public function test_catalog_covers_exactly_the_fifteen_blueprint_enums(): void
    {
        $this->assertCount(15, self::enums());

        $files = glob(dirname(__DIR__, 3).'/app/Enums/*.php');

        $this->assertCount(15, $files, 'app/Enums harus berisi tepat 15 enum.');
    }

    public function test_gender_uses_the_single_letter_codes(): void
    {
        $this->assertSame(['L', 'P'], Gender::values());
    }

    public function test_helper_methods_follow_the_blueprint(): void
    {
        $this->assertTrue(PaymentType::Bpjs->requiresBpjsNumber());
        $this->assertFalse(PaymentType::Umum->requiresBpjsNumber());
        $this->assertFalse(PaymentType::Asuransi->requiresBpjsNumber());
        $this->assertTrue(PaymentType::Asuransi->requiresInsurancePartner());
        $this->assertFalse(PaymentType::Bpjs->requiresInsurancePartner());
        $this->assertTrue(PaymentType::Asuransi->allowsPolicyNumber());
        $this->assertFalse(PaymentType::Umum->allowsPolicyNumber());

        $this->assertTrue(SessionStatus::Open->isOpen());
        $this->assertFalse(SessionStatus::Open->blocksNewBookings());

        foreach ([SessionStatus::Closed, SessionStatus::NeedsReview, SessionStatus::Cancelled] as $status) {
            $this->assertFalse($status->isOpen());
            $this->assertTrue($status->blocksNewBookings());
        }

        $this->assertTrue(ExceptionType::DoctorLeave->requiresDoctor());
        $this->assertFalse(ExceptionType::HospitalHoliday->requiresDoctor());
        $this->assertFalse(ExceptionType::NationalHoliday->requiresDoctor());
        $this->assertFalse(ExceptionType::Other->requiresDoctor());
        $this->assertTrue(ExceptionType::HospitalHoliday->forbidsDoctor());
        $this->assertTrue(ExceptionType::NationalHoliday->forbidsDoctor());
        $this->assertFalse(ExceptionType::DoctorLeave->forbidsDoctor());
        $this->assertFalse(ExceptionType::Other->forbidsDoctor());

        $this->assertTrue(DoctorStatus::Active->isActive());
        $this->assertFalse(DoctorStatus::Inactive->isActive());
        $this->assertFalse(DoctorStatus::Archived->isActive());

        $this->assertTrue(AccountType::Staff->isStaff());
        $this->assertFalse(AccountType::Staff->isPatient());
        $this->assertTrue(AccountType::Patient->isPatient());
        $this->assertFalse(AccountType::Patient->isStaff());
    }

    public function test_availability_helpers_follow_the_blueprint(): void
    {
        foreach (Availability::cases() as $availability) {
            $this->assertSame($availability === Availability::Available, $availability->isBookable(), $availability->name);
        }

        $this->assertSame(Availability::Leave, Availability::forException(ExceptionType::DoctorLeave));
        $this->assertSame(Availability::Holiday, Availability::forException(ExceptionType::HospitalHoliday));
        $this->assertSame(Availability::Holiday, Availability::forException(ExceptionType::NationalHoliday));
        $this->assertSame(Availability::Cancelled, Availability::forException(ExceptionType::Other));
        $this->assertSame(Availability::Cancelled, Availability::forException(null));
    }
}
