<?php

namespace Tests\Feature\View;

use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class FormCheckboxArrayTest extends TestCase
{
    use InteractsWithFormState;

    public function test_array_checkbox_keeps_the_bracket_name_and_derives_a_unique_id_per_value(): void
    {
        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Bedah" :value="12" />'
        );

        $this->assertStringContainsString('<input type="checkbox" id="specialty_ids-3" name="specialty_ids[]" value="3"', $html);
        $this->assertStringContainsString('<input type="checkbox" id="specialty_ids-12" name="specialty_ids[]" value="12"', $html);
        $this->assertStringContainsString('<label class="form-check-label" for="specialty_ids-3">Anak</label>', $html);
        $this->assertStringContainsString('<label class="form-check-label" for="specialty_ids-12">Bedah</label>', $html);
        $this->assertSame(2, substr_count($html, 'type="checkbox"'));
        $this->assertStringNotContainsString('type="hidden"', $html);
        $this->assertStringNotContainsString(' checked', $html);
    }

    public function test_array_checkbox_honours_an_explicit_id(): void
    {
        $this->blade('<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" id="spesialisasi-anak" />')
            ->assertSee('id="spesialisasi-anak"', false)
            ->assertSee('for="spesialisasi-anak"', false)
            ->assertDontSee('id="specialty_ids-3"', false);
    }

    public function test_checked_values_prop_checks_matching_values_with_loose_types(): void
    {
        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" :checked-values="$checked" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Bedah" :value="4" :checked-values="$checked" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Gigi" value="5" :checked-values="$checked" />',
            ['checked' => collect([3, '5'])]
        );

        $this->assertStringContainsString('value="3" checked', $html);
        $this->assertStringContainsString('value="5" checked', $html);
        $this->assertStringNotContainsString('value="4" checked', $html);
    }

    public function test_old_input_overrides_checked_values(): void
    {
        $this->withOldInput(['specialty_ids' => ['5']]);

        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" :checked-values="[3]" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Gigi" :value="5" :checked-values="[3]" />'
        );

        $this->assertStringContainsString('value="5" checked', $html);
        $this->assertStringNotContainsString('value="3" checked', $html);
    }

    public function test_old_input_without_the_key_means_nothing_is_checked(): void
    {
        $this->withOldInput(['name' => 'Budi']);

        $this->blade('<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" :checked-values="[3]" checked />')
            ->assertDontSee('checked', false);
    }

    public function test_group_error_is_shared_by_all_boxes_and_rendered_once_with_show_error_false(): void
    {
        $this->withViewErrors(['specialty_ids' => ['Pilih minimal satu spesialisasi.']]);

        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Bedah" :value="12" :show-error="false" />'
        );

        $this->assertSame(2, substr_count($html, 'form-check-input is-invalid'));
        $this->assertSame(2, substr_count($html, 'aria-invalid="true"'));
        $this->assertSame(2, substr_count($html, 'aria-describedby="specialty_ids-error"'));
        $this->assertSame(1, substr_count($html, '<div id="specialty_ids-error" class="invalid-feedback d-block">Pilih minimal satu spesialisasi.</div>'));
    }

    public function test_group_error_is_not_rendered_by_any_box_when_all_use_show_error_false(): void
    {
        // Pola form dokter (K3): form merender sendiri satu elemen galat dengan id `specialty_ids-error`.
        $this->withViewErrors(['specialty_ids' => ['Pilih minimal satu spesialisasi.']]);

        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" :show-error="false" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Bedah" :value="12" :show-error="false" />'
        );

        $this->assertStringNotContainsString('Pilih minimal satu spesialisasi.', $html);
        $this->assertSame(2, substr_count($html, 'aria-describedby="specialty_ids-error"'));
        $this->assertSame(2, substr_count($html, 'aria-invalid="true"'));
    }

    public function test_wildcard_error_key_is_read_for_array_names(): void
    {
        $this->withViewErrors(['specialty_ids.1' => ['Spesialisasi tidak boleh dipilih dua kali.']]);

        $this->blade('<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" />')
            ->assertSee('<div id="specialty_ids-error" class="invalid-feedback d-block">Spesialisasi tidak boleh dipilih dua kali.</div>', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_array_checkbox_escapes_value_and_sanitises_it_inside_the_id(): void
    {
        $html = (string) $this->blade('<x-form.checkbox name="specialty_ids[]" label="X" :value="$value" />', ['value' => '"><x>']);

        $this->assertStringContainsString('id="specialty_ids-x"', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;x&gt;"', $html);
        $this->assertStringNotContainsString('<x>', $html);
    }

    public function test_single_checkbox_is_unchanged_by_the_array_support(): void
    {
        $html = (string) $this->blade('<x-form.checkbox name="is_active" label="Aktif" :checked-values="[1]" :show-error="false" />');

        $this->assertStringContainsString('id="is_active"', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringNotContainsString('checked', $html);

        $this->withViewErrors(['is_active' => ['Status aktif tidak valid.']]);

        $this->blade('<x-form.checkbox name="is_active" label="Aktif" :show-error="false" />')
            ->assertSee('<div id="is_active-error" class="invalid-feedback d-block">Status aktif tidak valid.</div>', false);
    }

    public function test_bracketed_non_array_names_keep_the_phase_one_id(): void
    {
        $this->withViewErrors(['items.0.active' => ['Wajib.']]);

        $this->blade('<x-form.checkbox name="items[0][active]" label="Aktif" />')
            ->assertSee('id="items-0-active"', false)
            ->assertSee('<div id="items-0-active-error" class="invalid-feedback d-block">Wajib.</div>', false);
    }
}
