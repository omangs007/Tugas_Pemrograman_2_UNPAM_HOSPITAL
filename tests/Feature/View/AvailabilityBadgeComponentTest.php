<?php

namespace Tests\Feature\View;

use App\Enums\Availability;
use App\View\Components\AvailabilityBadge;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `x-availability-badge` (10 §3.4, F3-R13): komponen kelas yang memakai enum Availability apa adanya.
 */
class AvailabilityBadgeComponentTest extends TestCase
{
    /** @return array<string, array{Availability}> */
    public static function cases(): array
    {
        $cases = [];

        foreach (Availability::cases() as $case) {
            $cases[$case->value] = [$case];
        }

        return $cases;
    }

    private function render(Availability $availability, string $attributes = ''): string
    {
        return Blade::render('<x-availability-badge :availability="$availability" '.$attributes.' />', ['availability' => $availability]);
    }

    #[DataProvider('cases')]
    public function test_every_case_renders_icon_text_and_colour_from_the_enum(Availability $availability): void
    {
        $html = $this->render($availability);

        $this->assertStringContainsString('text-bg-'.$availability->color(), $html);
        $this->assertStringContainsString('<i class="bi '.$availability->icon().'" aria-hidden="true"></i>', $html);
        $this->assertStringContainsString($availability->label(), $html);
        $this->assertStringContainsString('data-availability="'.$availability->value.'"', $html);
    }

    public function test_available_with_remaining_reads_tersedia_sisa_n(): void
    {
        $this->assertStringContainsString('Tersedia · sisa 5', $this->render(Availability::Available, ':remaining="5"'));
        $this->assertStringContainsString('Tersedia · sisa 0', $this->render(Availability::Available, ':remaining="0"'));
    }

    public function test_available_without_remaining_reads_only_the_label(): void
    {
        $html = $this->render(Availability::Available);

        $this->assertStringContainsString('Tersedia', $html);
        $this->assertStringNotContainsString('sisa', $html);
    }

    public function test_compact_mode_shows_sisa_n_and_keeps_tersedia_for_screen_readers(): void
    {
        $html = $this->render(Availability::Available, ':remaining="12" :compact="true"');

        $this->assertStringContainsString('Sisa 12', $html);
        $this->assertStringContainsString('<span class="visually-hidden">Tersedia, </span>', $html);
        $this->assertStringNotContainsString('Tersedia · sisa', $html);
    }

    public function test_remaining_is_ignored_for_every_other_availability(): void
    {
        foreach (Availability::cases() as $availability) {
            if ($availability === Availability::Available) {
                continue;
            }

            $html = $this->render($availability, ':remaining="7" :compact="true"');

            $this->assertStringContainsString($availability->label(), $html);
            $this->assertStringNotContainsString('Sisa 7', $html, $availability->value);
            $this->assertStringNotContainsString('visually-hidden', $html, $availability->value);
        }
    }

    public function test_extra_classes_are_merged_and_no_inline_handlers_are_rendered(): void
    {
        $html = $this->render(Availability::Full, 'class="ms-2"');

        $this->assertStringContainsString('class="badge text-bg-danger availability-badge ms-2"', $html);
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
    }

    public function test_it_is_a_class_component(): void
    {
        $this->assertTrue(class_exists(AvailabilityBadge::class));
        $this->assertFileDoesNotExist(resource_path('views/components/availability-badge.php'));
        $this->assertFileExists(resource_path('views/components/availability-badge.blade.php'));
    }
}
