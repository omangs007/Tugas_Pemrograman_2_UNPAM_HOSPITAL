<?php

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class FormComponentsTest extends TestCase
{
    use InteractsWithFormState;

    public function test_input_renders_label_control_and_required_marker(): void
    {
        $this->blade('<x-form.input name="email" label="Email" type="email" required autocomplete="email" inputmode="email" maxlength="150" placeholder="nama@contoh.test" />')
            ->assertSee('<label for="email" class="form-label fw-semibold">Email <span class="required-mark" aria-hidden="true">*</span></label>', false)
            ->assertSee('type="email"', false)
            ->assertSee('id="email"', false)
            ->assertSee('name="email"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('inputmode="email"', false)
            ->assertSee('maxlength="150"', false)
            ->assertSee('placeholder="nama@contoh.test"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('class="form-control"', false)
            ->assertDontSee('aria-invalid', false)
            ->assertDontSee('aria-describedby', false)
            ->assertDontSee('is-invalid', false)
            ->assertDontSee('value=', false);
    }

    public function test_input_without_required_has_no_marker_or_aria_required(): void
    {
        $this->blade('<x-form.input name="phone" label="Telepon" />')
            ->assertSee('Telepon</label>', false)
            ->assertDontSee('required-mark', false)
            ->assertDontSee('aria-required', false)
            ->assertSee('type="text"', false);
    }

    public function test_input_with_error_exposes_invalid_state_and_links_help_and_error(): void
    {
        $this->withViewErrors(['email' => ['Format email tidak valid.']]);

        $this->blade('<x-form.input name="email" label="Email" help="Gunakan email aktif." />')
            ->assertSee('class="form-control is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="email-help email-error"', false)
            ->assertSee('<div id="email-help" class="form-text">Gunakan email aktif.</div>', false)
            ->assertSee('<div id="email-error" class="invalid-feedback d-block">Format email tidak valid.</div>', false);
    }

    public function test_input_describedby_lists_only_existing_parts(): void
    {
        $this->blade('<x-form.input name="email" label="Email" help="Bantuan." />')
            ->assertSee('aria-describedby="email-help"', false)
            ->assertDontSee('email-error', false);

        $this->withViewErrors(['email' => ['Wajib diisi.']]);

        $this->blade('<x-form.input name="email" label="Email" />')
            ->assertSee('aria-describedby="email-error"', false)
            ->assertDontSee('email-help', false);
    }

    public function test_input_only_shows_the_error_of_its_own_field(): void
    {
        $this->withViewErrors(['password' => ['Password wajib diisi.']]);

        $this->blade('<x-form.input name="email" label="Email" />')
            ->assertDontSee('Password wajib diisi.')
            ->assertDontSee('is-invalid', false);
    }

    public function test_input_uses_value_prop_and_old_input_takes_precedence(): void
    {
        $this->blade('<x-form.input name="email" label="Email" value="asli@contoh.test" />')
            ->assertSee('value="asli@contoh.test"', false);

        $this->withOldInput(['email' => 'lama@contoh.test']);

        $this->blade('<x-form.input name="email" label="Email" value="asli@contoh.test" />')
            ->assertSee('value="lama@contoh.test"', false)
            ->assertDontSee('asli@contoh.test');
    }

    public function test_password_input_is_never_refilled(): void
    {
        $this->withOldInput(['password' => 'rahasia123']);

        $this->blade('<x-form.input name="password" label="Password" type="password" value="nilai-prop" autocomplete="current-password" />')
            ->assertSee('type="password"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertDontSee('rahasia123')
            ->assertDontSee('nilai-prop')
            ->assertDontSee('value=', false);
    }

    public function test_input_escapes_label_help_value_placeholder_and_error(): void
    {
        $this->withViewErrors(['nama' => ['<script>alert(1)</script>']]);

        $this->blade(
            '<x-form.input name="nama" :label="$p" :help="$p" :value="$p" :placeholder="$p" />',
            ['p' => '"><script>alert(1)</script>'],
        )
            ->assertSee('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_input_derives_id_from_dotted_and_bracketed_names(): void
    {
        $this->withViewErrors(['items.0.name' => ['Nama wajib diisi.']]);

        $this->blade('<x-form.input name="items[0][name]" label="Nama item" />')
            ->assertSee('id="items-0-name"', false)
            ->assertSee('for="items-0-name"', false)
            ->assertSee('name="items[0][name]"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('<div id="items-0-name-error" class="invalid-feedback d-block">Nama wajib diisi.</div>', false);

        $this->blade('<x-form.input name="schedule.start" label="Mulai" />')
            ->assertSee('id="schedule-start"', false);

        $this->blade('<x-form.input name="email" label="Email" id="kustom" />')
            ->assertSee('for="kustom"', false)
            ->assertSee('id="kustom"', false);
    }

    public function test_input_reads_old_input_for_bracketed_names(): void
    {
        $this->withOldInput(['items' => [['name' => 'Budi']]]);

        $this->blade('<x-form.input name="items[0][name]" label="Nama item" />')
            ->assertSee('value="Budi"', false);
    }

    public function test_input_supports_prepend_and_append_slots(): void
    {
        $this->blade(<<<'BLADE'
            <x-form.input name="phone" label="Telepon">
                <x-slot:prepend><span class="input-group-text">+62</span></x-slot:prepend>
                <x-slot:append><span class="input-group-text">WA</span></x-slot:append>
            </x-form.input>
            BLADE)
            ->assertSee('input-group has-validation', false)
            ->assertSeeInOrder(['+62', '<input', 'WA'], false);

        $this->blade('<x-form.input name="phone" label="Telepon" />')
            ->assertDontSee('input-group', false);
    }

    public function test_input_merges_extra_attributes(): void
    {
        $this->blade('<x-form.input name="email" label="Email" class="text-lowercase" data-x="1" readonly />')
            ->assertSee('class="form-control text-lowercase"', false)
            ->assertSee('data-x="1"', false)
            ->assertSee('readonly', false);
    }

    public function test_errors_summary_lists_errors_as_links_and_is_focusable(): void
    {
        $this->withViewErrors([
            'name' => ['Nama wajib diisi.'],
            'items.0.name' => ['Nama item wajib diisi.', 'Nama item terlalu pendek.'],
        ]);

        $this->blade('<x-form.errors-summary />')
            ->assertSee('role="alert"', false)
            ->assertSee('tabindex="-1"', false)
            ->assertSee('data-errors-summary', false)
            ->assertSee('Periksa kembali isian Anda')
            ->assertSee('<a href="#name" class="alert-link">Nama wajib diisi.</a>', false)
            ->assertSee('<a href="#items-0-name" class="alert-link">Nama item wajib diisi.</a>', false)
            ->assertSee('<a href="#items-0-name" class="alert-link">Nama item terlalu pendek.</a>', false);
    }

    public function test_errors_summary_accepts_a_custom_title_and_escapes_messages(): void
    {
        $this->withViewErrors(['name' => ['<script>alert(1)</script>']]);

        $this->blade('<x-form.errors-summary title="Ada kesalahan" />')
            ->assertSee('Ada kesalahan')
            ->assertDontSee('Periksa kembali isian Anda')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function controlsWithBracketedName(): array
    {
        return [
            'input' => ['<x-form.input name="items[0][name]" label="Nama item" />'],
            'select' => ['<x-form.select name="items[0][name]" label="Nama item" :options="[\'a\' => \'Opsi A\']" />'],
            'textarea' => ['<x-form.textarea name="items[0][name]" label="Nama item" />'],
            'checkbox' => ['<x-form.checkbox name="items[0][name]" label="Nama item" />'],
            'radio-group' => ['<x-form.radio-group name="items[0][name]" legend="Nama item" :options="[\'a\' => \'Opsi A\']" />'],
            'file' => ['<x-form.file name="items[0][name]" label="Nama item" />'],
        ];
    }

    #[DataProvider('controlsWithBracketedName')]
    public function test_every_control_derives_the_same_id_that_errors_summary_links_to(string $control): void
    {
        $this->withViewErrors(['items.0.name' => ['Nama item wajib diisi.']]);

        $this->blade($control)
            ->assertSee('id="items-0-name"', false)
            ->assertSee('<div id="items-0-name-error" class="invalid-feedback d-block">Nama item wajib diisi.</div>', false);

        $this->blade('<x-form.errors-summary />')
            ->assertSee('<a href="#items-0-name" class="alert-link">Nama item wajib diisi.</a>', false);
    }

    public function test_errors_summary_renders_nothing_without_errors(): void
    {
        $this->blade('<x-form.errors-summary />')
            ->assertDontSee('data-errors-summary', false)
            ->assertDontSee('alert', false);

        $this->withViewErrors([]);

        $this->blade('<x-form.errors-summary />')
            ->assertDontSee('data-errors-summary', false);
    }
}
