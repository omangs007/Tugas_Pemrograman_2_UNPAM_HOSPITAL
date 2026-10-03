<?php

namespace Tests\Feature\View;

use App\Enums\PaymentType;
use Illuminate\View\ViewException;
use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class FormChoiceComponentsTest extends TestCase
{
    use InteractsWithFormState;

    // ------------------------------------------------------------------ select

    public function test_select_renders_enum_options_with_disabled_placeholder(): void
    {
        $this->blade('<x-form.select name="payment_type" label="Jenis pembayaran" :options="$options" placeholder="Pilih jenis pembayaran" required />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('<label for="payment_type" class="form-label fw-semibold">Jenis pembayaran <span class="required-mark" aria-hidden="true">*</span></label>', false)
            ->assertSee('id="payment_type"', false)
            ->assertSee('name="payment_type"', false)
            ->assertSee('class="form-select"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('<option value="" disabled selected>Pilih jenis pembayaran</option>', false)
            ->assertSeeInOrder(['Umum/Pribadi', 'BPJS Kesehatan', 'Asuransi']);
    }

    public function test_select_marks_the_selected_value_from_prop_or_backed_enum(): void
    {
        $this->blade('<x-form.select name="payment_type" label="Jenis pembayaran" :options="$options" selected="bpjs" placeholder="Pilih" />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('<option value="bpjs" selected>BPJS Kesehatan</option>', false)
            ->assertDontSee('<option value="" disabled selected>', false)
            ->assertDontSee('<option value="umum" selected', false);

        $this->blade('<x-form.select name="payment_type" label="Jenis pembayaran" :options="$options" :selected="$selected" />', [
            'options' => PaymentType::options(),
            'selected' => PaymentType::Asuransi,
        ])
            ->assertSee('<option value="asuransi" selected>Asuransi</option>', false);
    }

    public function test_select_old_input_takes_precedence_over_selected(): void
    {
        $this->withOldInput(['payment_type' => 'asuransi']);

        $this->blade('<x-form.select name="payment_type" label="Jenis pembayaran" :options="$options" selected="umum" />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('<option value="asuransi" selected>Asuransi</option>', false)
            ->assertDontSee('<option value="umum" selected', false);
    }

    public function test_select_accepts_collections(): void
    {
        $this->blade('<x-form.select name="clinic_id" label="Poliklinik" :options="$options" :selected="2" />', [
            'options' => collect([1 => 'Poli Anak', 2 => 'Poli Gigi']),
        ])
            ->assertSee('<option value="1" >Poli Anak</option>', false)
            ->assertSee('<option value="2" selected>Poli Gigi</option>', false);
    }

    public function test_select_multiple_appends_brackets_and_selects_many(): void
    {
        $this->blade('<x-form.select name="tags" label="Label" :options="$options" :selected="$selected" multiple placeholder="Abaikan" />', [
            'options' => [1 => 'A', 2 => 'B', 3 => 'C'],
            'selected' => [1, 3],
        ])
            ->assertSee('name="tags[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('<option value="1" selected>A</option>', false)
            ->assertSee('<option value="3" selected>C</option>', false)
            ->assertDontSee('<option value="2" selected', false)
            ->assertDontSee('Abaikan');
    }

    public function test_select_error_state_and_escaping(): void
    {
        $this->withViewErrors(['clinic_id' => ['Pilihan tidak valid.']]);

        $this->blade('<x-form.select name="clinic_id" label="Poliklinik" :options="$options" help="Pilih satu." />', [
            'options' => ['<x>' => '<script>alert(1)</script>'],
        ])
            ->assertSee('class="form-select is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="clinic_id-help clinic_id-error"', false)
            ->assertSee('<div id="clinic_id-error" class="invalid-feedback d-block">Pilihan tidak valid.</div>', false)
            ->assertSee('value="&lt;x&gt;"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ------------------------------------------------------------------ textarea

    public function test_textarea_renders_value_rows_and_character_counter(): void
    {
        $this->blade('<x-form.textarea name="complaint" label="Keluhan" value="Halo!" :rows="6" maxlength="500" required />')
            ->assertSee('rows="6"', false)
            ->assertSee('maxlength="500"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('class="form-control"', false)
            ->assertSee('>Halo!</textarea>', false)
            ->assertSee('data-char-counter', false)
            ->assertSee('data-counter-for="complaint"', false)
            ->assertSee('data-counter-max="500"', false)
            ->assertSee('>5 / 500</span>', false)
            ->assertSee('aria-live="polite" data-counter-announce', false);
    }

    public function test_textarea_defaults_to_four_rows_and_has_no_counter_without_maxlength(): void
    {
        $this->blade('<x-form.textarea name="notes" label="Catatan" />')
            ->assertSee('rows="4"', false)
            ->assertSee('></textarea>', false)
            ->assertDontSee('data-char-counter', false);
    }

    public function test_textarea_old_input_error_and_escaping(): void
    {
        $this->withOldInput(['notes' => '</textarea><script>alert(1)</script>']);
        $this->withViewErrors(['notes' => ['Catatan terlalu panjang.']]);

        $this->blade('<x-form.textarea name="notes" label="Catatan" value="abaikan" />')
            ->assertSee('&lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('abaikan')
            ->assertSee('class="form-control is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="notes-error"', false);
    }

    // ------------------------------------------------------------------ checkbox

    public function test_checkbox_defaults_to_unchecked_with_value_one(): void
    {
        $this->blade('<x-form.checkbox name="is_active" label="Aktif" />')
            ->assertSee('<div class="form-check">', false)
            ->assertSee('type="checkbox"', false)
            ->assertSee('value="1"', false)
            ->assertSee('class="form-check-input"', false)
            ->assertSee('<label class="form-check-label" for="is_active">Aktif</label>', false)
            ->assertDontSee('checked', false)
            ->assertDontSee('type="hidden"', false);
    }

    public function test_checkbox_with_hidden_sends_zero_and_respects_checked_prop(): void
    {
        $this->blade('<x-form.checkbox name="is_active" label="Aktif" with-hidden checked />')
            ->assertSee('<input type="hidden" name="is_active" value="0">', false)
            ->assertSee('value="1" checked', false);
    }

    public function test_checkbox_old_input_overrides_the_checked_prop(): void
    {
        $this->withOldInput(['is_active' => '0']);

        $this->blade('<x-form.checkbox name="is_active" label="Aktif" with-hidden checked />')
            ->assertDontSee('checked', false);

        $this->withOldInput(['is_active' => '1']);

        $this->blade('<x-form.checkbox name="is_active" label="Aktif" with-hidden />')
            ->assertSee('value="1" checked', false);
    }

    public function test_checkbox_without_hidden_is_unchecked_when_old_input_exists_without_its_key(): void
    {
        $this->withOldInput(['name' => 'Budi']);

        $this->blade('<x-form.checkbox name="agree" label="Setuju" checked />')
            ->assertDontSee('checked', false);
    }

    public function test_checkbox_required_error_and_escaping(): void
    {
        $this->withViewErrors(['agree' => ['Anda harus menyetujui ketentuan.']]);

        $this->blade('<x-form.checkbox name="agree" :label="$p" help="Wajib dicentang." required />', ['p' => '<script>alert(1)</script>'])
            ->assertSee('aria-required="true"', false)
            ->assertSee('<span class="required-mark" aria-hidden="true">*</span>', false)
            ->assertSee('class="form-check-input is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="agree-help agree-error"', false)
            ->assertSee('<div id="agree-error" class="invalid-feedback d-block">Anda harus menyetujui ketentuan.</div>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ------------------------------------------------------------------ radio-group

    public function test_radio_group_renders_fieldset_legend_and_options(): void
    {
        $this->blade('<x-form.radio-group name="payment_type" legend="Jenis pembayaran" :options="$options" selected="bpjs" required />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('<fieldset id="payment_type" role="radiogroup" tabindex="-1"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('class="mb-3"', false)
            ->assertSee('<legend class="form-label fw-semibold fs-6">Jenis pembayaran <span class="required-mark" aria-hidden="true">*</span></legend>', false)
            ->assertSee('id="payment_type-umum"', false)
            ->assertSee('id="payment_type-bpjs"', false)
            ->assertSee('<label class="form-check-label" for="payment_type-bpjs">BPJS Kesehatan</label>', false)
            ->assertSee('value="bpjs" checked', false)
            ->assertDontSee('value="umum" checked', false)
            ->assertDontSee('form-check-inline', false);
    }

    public function test_radio_group_inline_old_input_and_backed_enum_selection(): void
    {
        $this->withOldInput(['payment_type' => 'asuransi']);

        $this->blade('<x-form.radio-group name="payment_type" legend="Jenis pembayaran" :options="$options" :selected="$selected" inline />', [
            'options' => PaymentType::options(),
            'selected' => PaymentType::Umum,
        ])
            ->assertSee('form-check form-check-inline', false)
            ->assertSee('value="asuransi" checked', false)
            ->assertDontSee('value="umum" checked', false);
    }

    public function test_radio_group_error_is_linked_to_the_fieldset(): void
    {
        $this->withViewErrors(['payment_type' => ['Jenis pembayaran wajib dipilih.']]);

        $this->blade('<x-form.radio-group name="payment_type" legend="Jenis pembayaran" :options="$options" help="Pilih salah satu." />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="payment_type-help payment_type-error"', false)
            ->assertSee('<div id="payment_type-help" class="form-text">Pilih salah satu.</div>', false)
            ->assertSee('<div id="payment_type-error" class="invalid-feedback d-block">Jenis pembayaran wajib dipilih.</div>', false)
            ->assertSee('form-check-input is-invalid', false);
    }

    public function test_radio_group_escapes_legend_and_option_values(): void
    {
        $this->blade('<x-form.radio-group name="x" :legend="$p" :options="$options" />', [
            'p' => '<script>alert(1)</script>',
            'options' => ['"><script>' => '<b>bold</b>'],
        ])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;b&gt;bold&lt;/b&gt;', false)
            ->assertDontSee('<script>', false);
    }

    public function test_radio_group_requires_a_legend(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('legend');

        $this->blade('<x-form.radio-group name="x" :options="[]" />');
    }
}
