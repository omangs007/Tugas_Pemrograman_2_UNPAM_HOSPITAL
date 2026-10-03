@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'rows' => 4,
    'maxlength' => null,
    'value' => null,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $current = old($key, $value);
    $current = is_scalar($current) ? (string) $current : '';
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"@if ($maxlength) maxlength="{{ $maxlength }}"@endif @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-control', 'is-invalid' => $error !== '']) }}>{{ $current }}</textarea>
    @if ($maxlength)
        <div class="form-text text-end"><span data-char-counter data-counter-for="{{ $id }}" data-counter-max="{{ $maxlength }}" aria-hidden="true">{{ mb_strlen($current) }} / {{ $maxlength }}</span><span class="visually-hidden" aria-live="polite" data-counter-announce></span></div>
    @endif
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
