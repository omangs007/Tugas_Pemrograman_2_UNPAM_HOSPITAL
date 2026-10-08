<?php

namespace Tests\Feature\View;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `x-wizard-steps` (00 J.5, F6-R17): lima langkah berlabel tetap, langkah selesai berupa tautan, langkah aktif
 * `aria-current="step"`, langkah berikutnya bukan tautan, ringkasan "Langkah n dari 5" untuk layar kecil.
 */
class WizardStepsComponentTest extends TestCase
{
    /** @param  array<int, string>  $links */
    private function render(int $current, array $links = []): string
    {
        return Blade::render('<x-wizard-steps :current="$current" :links="$links" />', ['current' => $current, 'links' => $links]);
    }

    public function test_it_renders_the_five_fixed_labels_in_order_inside_a_labelled_nav(): void
    {
        $html = $this->render(1);

        $this->assertStringContainsString('<nav aria-label="Langkah pendaftaran"', $html);
        $this->assertSame(5, substr_count($html, '<li'));

        $positions = [];

        foreach (['Poliklinik', 'Dokter', 'Tanggal &amp; Sesi', 'Data Pendaftaran', 'Konfirmasi'] as $label) {
            $position = strpos($html, '>'.$label.'<', (int) end($positions));
            $this->assertNotFalse($position, $label);
            $positions[] = $position;
        }
    }

    public function test_the_current_step_is_marked_and_completed_steps_are_links(): void
    {
        $html = $this->render(3, [1 => '/a?x=1&y=2', 2 => '/b', 3 => '/c', 4 => '/d']);

        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertStringContainsString('href="/a?x=1&amp;y=2"', $html);
        $this->assertStringContainsString('href="/b"', $html);
        $this->assertStringNotContainsString('href="/c"', $html, 'Langkah aktif bukan tautan.');
        $this->assertStringNotContainsString('href="/d"', $html, 'Langkah berikutnya bukan tautan.');
        $this->assertSame(2, substr_count($html, 'Langkah selesai'));
    }

    public function test_a_completed_step_without_a_url_is_plain_text(): void
    {
        $html = $this->render(3, [1 => '/a']);

        $this->assertSame(1, substr_count($html, '<a '));
        $this->assertStringContainsString('>Dokter<', $html);
    }

    public function test_the_small_screen_summary_names_the_current_step(): void
    {
        $this->assertStringContainsString('Langkah 3 dari 5: Tanggal &amp; Sesi', $this->render(3));
        $this->assertStringContainsString('Langkah 1 dari 5: Poliklinik', $this->render(1));
        $this->assertStringContainsString('Langkah 5 dari 5: Konfirmasi', $this->render(5));
    }

    public function test_the_current_step_is_clamped_to_the_five_steps(): void
    {
        $this->assertStringContainsString('Langkah 5 dari 5: Konfirmasi', $this->render(9));
        $this->assertStringContainsString('Langkah 1 dari 5: Poliklinik', $this->render(0));
    }

    public function test_the_first_step_has_no_completed_steps_and_the_last_has_four(): void
    {
        $this->assertStringNotContainsString('Langkah selesai', $this->render(1, [1 => '/a']));
        $this->assertSame(4, substr_count($this->render(5, [1 => '/a', 2 => '/b', 3 => '/c', 4 => '/d']), 'Langkah selesai'));
    }
}
