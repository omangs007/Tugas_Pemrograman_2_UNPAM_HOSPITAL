<?php

namespace Tests\Feature\View;

use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class RichTextComponentTest extends TestCase
{
    use InteractsWithFormState;

    private function render(string $attributes = '', array $data = []): string
    {
        return (string) $this->blade('<x-form.rich-text name="description" label="Deskripsi" '.$attributes.' />', $data);
    }

    public function test_renders_label_textarea_and_data_contract(): void
    {
        $html = $this->render(':value="$value" help="Gunakan judul dan daftar." required :maxlength="30000"', ['value' => '<p>Halo</p>']);

        $this->assertStringContainsString('data-rich-text', $html);
        $this->assertStringContainsString('data-upload-url="'.route('admin.media.editor-upload').'"', $html);
        $this->assertStringContainsString('data-min-height="240"', $html);
        $this->assertStringContainsString('data-max-length="30000"', $html);
        $this->assertStringContainsString('<label id="description-label" for="description" class="form-label fw-semibold">Deskripsi <span class="required-mark" aria-hidden="true">*</span></label>', $html);
        $this->assertStringContainsString('<textarea id="description" name="description"', $html);
        $this->assertStringContainsString('data-rich-text-input', $html);
        $this->assertStringContainsString('aria-required="true"', $html);
        $this->assertStringContainsString('aria-describedby="description-help"', $html);
        $this->assertStringContainsString('<div id="description-help" class="form-text">Gunakan judul dan daftar.</div>', $html);
        $this->assertStringContainsString('&lt;p&gt;Halo&lt;/p&gt;</textarea>', $html);
        $this->assertDoesNotMatchRegularExpression('/<textarea[^>]*\srequired[\s>]/', $html, 'required HTML pada textarea tersembunyi memblokir submit');
    }

    public function test_editor_container_is_hidden_until_javascript_enables_it(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('/<div[^>]*\shidden[^>]*data-rich-text-editor|<div[^>]*data-rich-text-editor[^>]*\shidden/', $html);
        $this->assertStringContainsString('data-rich-text-surface', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('data-rich-text-status', $html);
    }

    public function test_toolbar_is_labelled_and_all_buttons_are_accessible_non_submit_buttons(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('role="toolbar"', $html);
        $this->assertStringContainsString('aria-label="Pemformatan teks: Deskripsi"', $html);

        foreach (['ql-header" value="2"', 'ql-header" value="3"', 'ql-header" value="4"', 'ql-bold', 'ql-italic', 'ql-underline', 'ql-list" value="ordered"', 'ql-list" value="bullet"', 'ql-blockquote', 'ql-link', 'ql-image', 'ql-clean'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        foreach (['Judul 2', 'Judul 3', 'Judul 4', 'Tebal', 'Miring', 'Garis bawah', 'Daftar bernomor', 'Daftar poin', 'Kutipan', 'Tautan', 'Sisipkan gambar', 'Bersihkan format'] as $label) {
            $this->assertStringContainsString('aria-label="'.$label.'"', $html);
        }

        $this->assertSame(substr_count($html, '<button'), substr_count($html, '<button type="button"'));
    }

    public function test_alt_text_modal_is_part_of_the_component(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="description-image-modal"', $html);
        $this->assertStringContainsString('data-rich-text-image-modal', $html);
        $this->assertStringContainsString('aria-labelledby="description-image-modal-title"', $html);
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $html);
        $this->assertStringContainsString('data-rich-text-image-file', $html);
        $this->assertStringContainsString('data-rich-text-image-alt', $html);
        $this->assertStringContainsString('maxlength="255"', $html);
        $this->assertStringContainsString('<label for="description-image-alt" class="form-label fw-semibold">Teks alternatif', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*data-rich-text-image-preview[^>]*alt=""|<img[^>]*alt=""[^>]*data-rich-text-image-preview/', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('data-rich-text-image-submit', $html);
        $this->assertStringContainsString('data-bs-dismiss="modal"', $html);
        $this->assertStringContainsString('Unggah dan sisipkan', $html);
    }

    public function test_has_no_inline_handlers_inline_styles_blob_urls_or_scripts(): void
    {
        $html = $this->render(':value="$value"', ['value' => 'x']);

        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString('blob:', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_old_input_overrides_value_and_error_state_is_linked(): void
    {
        $this->withOldInput(['description' => '<p>Isian lama</p>']);
        $this->withViewErrors(['description' => ['Deskripsi maksimal 30.000 karakter.']]);

        $html = $this->render(':value="$value" help="Bantuan."', ['value' => '<p>Dari DB</p>']);

        $this->assertStringContainsString('&lt;p&gt;Isian lama&lt;/p&gt;</textarea>', $html);
        $this->assertStringNotContainsString('Dari DB', $html);
        $this->assertStringContainsString('form-control is-invalid', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="description-help description-error"', $html);
        $this->assertStringContainsString('<div id="description-error" class="invalid-feedback d-block">Deskripsi maksimal 30.000 karakter.</div>', $html);
    }

    public function test_upload_url_and_min_height_are_configurable_and_escaped(): void
    {
        $html = $this->render('upload-url="/admin/media/editor?a=1&b=&quot;2&quot;" :min-height="400"');

        // Nilai literal `&quot;` di-escape ulang oleh {{ }} (double-encode): `&quot;` menjadi `&amp;quot;`.
        $this->assertStringContainsString('data-upload-url="/admin/media/editor?a=1&amp;b=&amp;quot;2&amp;quot;"', $html);
        $this->assertStringNotContainsString('b="2"', $html);
        $this->assertStringContainsString('data-min-height="400"', $html);
        $this->assertStringContainsString('data-min-height="120"', $this->render(':min-height="50"'));
    }

    public function test_id_is_derived_like_the_other_controls_for_bracketed_names(): void
    {
        $this->withViewErrors(['items.0.body' => ['Isi wajib diisi.']]);

        $html = (string) $this->blade('<x-form.rich-text name="items[0][body]" label="Isi" />');

        $this->assertStringContainsString('id="items-0-body"', $html);
        $this->assertStringContainsString('<div id="items-0-body-error" class="invalid-feedback d-block">Isi wajib diisi.</div>', $html);

        $this->blade('<x-form.errors-summary />')
            ->assertSee('<a href="#items-0-body" class="alert-link">Isi wajib diisi.</a>', false);
    }
}
