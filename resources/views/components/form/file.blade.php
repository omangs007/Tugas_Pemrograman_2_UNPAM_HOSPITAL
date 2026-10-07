@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'accept' => 'image/jpeg,image/png,image/webp',
    'maxKb' => 2048,
    'multiple' => false,
    'currentMedia' => null,
    'altName' => null,
    'altValue' => null,
    'placeholder' => null,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = '';
    if (isset($errors)) {
        $error = (string) ($errors->first($key) ?: ($multiple ? $errors->first($key.'.*') : ''));
    }
    $altName = $altName ?: $name.'_alt';
    $maxLabel = $maxKb >= 1024
        ? rtrim(rtrim(number_format($maxKb / 1024, 1, ',', '.'), '0'), ',').' MB'
        : $maxKb.' KB';
    $placeholderTypes = ['photo' => 'doctor', 'cover' => 'clinic', 'gallery' => 'gallery', 'photos' => 'gallery', 'image' => 'banner', 'logo' => 'logo', 'editor' => 'article'];
    $describedBy = trim($id.'-hint '.($help ? $id.'-help ' : '').($error !== '' ? $id.'-error' : ''));
@endphp
<div class="mb-3" data-image-field>
    <label for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    @if ($currentMedia)
        <div class="mb-2" data-current-media>
            <x-image :media="$currentMedia" :type="$placeholder ?? ($placeholderTypes[$currentMedia->collection] ?? 'gallery')" variant="thumb" :alt="$currentMedia->alt_text" class="image-preview" />
            <p class="form-text mb-0">Gambar saat ini</p>
        </div>
    @endif
    <input type="file" id="{{ $id }}" name="{{ $name }}@if ($multiple)[]@endif" accept="{{ $accept }}" data-image-input data-max-kb="{{ $maxKb }}"@if ($multiple) multiple @endif @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif aria-describedby="{{ $describedBy }}" {{ $attributes->class(['form-control', 'is-invalid' => $error !== '']) }}>
    <img data-image-preview class="image-preview mt-2 d-none" alt="Pratinjau gambar yang dipilih">
    <div data-image-warning class="form-text text-warning-emphasis d-none" role="status"></div>
    <div id="{{ $id }}-hint" class="form-text">Format JPG, PNG, atau WebP (SVG ditolak). Ukuran maksimal {{ $maxLabel }}.</div>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
    <div class="mt-3">
        <x-form.input
            :name="$altName"
            label="Teks alternatif gambar"
            :value="$altValue ?? $currentMedia?->alt_text"
            :required="true"
            maxlength="255"
            help="Jelaskan isi gambar dengan singkat untuk pembaca layar, mis. Foto dr. Andi tersenyum. Wajib diisi bila memilih gambar."
        />
    </div>
</div>
