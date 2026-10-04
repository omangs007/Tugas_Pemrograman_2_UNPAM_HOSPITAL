@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'value' => null,
    'maxlength' => null,
    'minHeight' => 240,
    'uploadUrl' => null,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $current = old($key, $value);
    $current = is_scalar($current) ? (string) $current : '';
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
    $uploadUrl = $uploadUrl ?: route('admin.media.editor-upload');
    $minHeight = max(120, (int) $minHeight);
    $modalId = $id.'-image-modal';
@endphp
<div class="mb-3 rich-text" data-rich-text data-upload-url="{{ $uploadUrl }}" data-min-height="{{ $minHeight }}"@if ($maxlength) data-max-length="{{ $maxlength }}"@endif>
    <label id="{{ $id }}-label" for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>

    {{-- Tanpa JavaScript, textarea ini adalah editornya; editor.js menyembunyikannya dan menyinkronkan nilainya saat submit. --}}
    <textarea id="{{ $id }}" name="{{ $name }}" rows="10"@if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif data-rich-text-input {{ $attributes->class(['form-control', 'is-invalid' => $error !== '']) }}>{{ $current }}</textarea>

    <div class="rich-text-editor" data-rich-text-editor hidden>
        <div role="toolbar" aria-label="Pemformatan teks: {{ $label }}" data-rich-text-toolbar>
            <span class="ql-formats">
                <button type="button" class="ql-header" value="2" aria-label="Judul 2" title="Judul 2"></button>
                <button type="button" class="ql-header" value="3" aria-label="Judul 3" title="Judul 3"></button>
                <button type="button" class="ql-header" value="4" aria-label="Judul 4" title="Judul 4"></button>
            </span>
            <span class="ql-formats">
                <button type="button" class="ql-bold" aria-label="Tebal" title="Tebal"></button>
                <button type="button" class="ql-italic" aria-label="Miring" title="Miring"></button>
                <button type="button" class="ql-underline" aria-label="Garis bawah" title="Garis bawah"></button>
            </span>
            <span class="ql-formats">
                <button type="button" class="ql-list" value="ordered" aria-label="Daftar bernomor" title="Daftar bernomor"></button>
                <button type="button" class="ql-list" value="bullet" aria-label="Daftar poin" title="Daftar poin"></button>
                <button type="button" class="ql-blockquote" aria-label="Kutipan" title="Kutipan"></button>
            </span>
            <span class="ql-formats">
                <button type="button" class="ql-link" aria-label="Tautan" title="Tautan"></button>
                <button type="button" class="ql-image" aria-label="Sisipkan gambar" title="Sisipkan gambar"></button>
                <button type="button" class="ql-clean" aria-label="Bersihkan format" title="Bersihkan format"></button>
            </span>
        </div>
        <div data-rich-text-surface></div>
    </div>

    <div class="visually-hidden" role="status" aria-live="polite" data-rich-text-status></div>
    <div id="{{ $id }}-feedback" class="invalid-feedback d-block" data-rich-text-feedback hidden></div>

    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif

    {{-- Modal alt text (dipindahkan ke <body> oleh editor.js). Kontrolnya tanpa atribut name: tidak ikut terkirim. --}}
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" data-bs-backdrop="static" aria-labelledby="{{ $modalId }}-title" aria-hidden="true" data-rich-text-image-modal>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="{{ $modalId }}-title">Sisipkan gambar</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="{{ $id }}-image-file" class="form-label fw-semibold">Berkas gambar <span class="required-mark" aria-hidden="true">*</span></label>
                        <input type="file" id="{{ $id }}-image-file" class="form-control" accept="image/jpeg,image/png,image/webp" aria-describedby="{{ $id }}-image-file-help" data-rich-text-image-file>
                        <div id="{{ $id }}-image-file-help" class="form-text">JPG, PNG, atau WebP, maksimal 2 MB, minimal 200 x 100 piksel. SVG ditolak.</div>
                    </div>
                    <img class="img-thumbnail rich-text-preview d-none mb-3" alt="" data-rich-text-image-preview>
                    <div class="mb-3">
                        <label for="{{ $id }}-image-alt" class="form-label fw-semibold">Teks alternatif <span class="required-mark" aria-hidden="true">*</span></label>
                        <input type="text" id="{{ $id }}-image-alt" class="form-control" maxlength="255" autocomplete="off" aria-describedby="{{ $id }}-image-alt-help" data-rich-text-image-alt>
                        <div id="{{ $id }}-image-alt-help" class="form-text">Jelaskan isi gambar untuk pembaca layar. Wajib diisi, maksimal 255 karakter.</div>
                    </div>
                    <div class="alert alert-danger d-none mb-0" role="alert" data-rich-text-image-error></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" data-rich-text-image-submit>Unggah dan sisipkan</button>
                </div>
            </div>
        </div>
    </div>
</div>
