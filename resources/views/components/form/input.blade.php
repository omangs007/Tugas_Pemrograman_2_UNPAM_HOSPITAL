@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'type' => 'text',
    'value' => null,
    'autocomplete' => null,
    'inputmode' => null,
    'maxlength' => null,
    'placeholder' => null,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $current = $type === 'password' ? '' : old($key, $value);
    $current = is_scalar($current) ? (string) $current : '';
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
    $grouped = isset($prepend) || isset($append);
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    @if ($grouped)<div class="input-group has-validation">@endif
    {{ $prepend ?? '' }}
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"@if ($current !== '') value="{{ $current }}"@endif @if ($autocomplete) autocomplete="{{ $autocomplete }}"@endif @if ($inputmode) inputmode="{{ $inputmode }}"@endif @if ($maxlength) maxlength="{{ $maxlength }}"@endif @if ($placeholder) placeholder="{{ $placeholder }}"@endif @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-control', 'is-invalid' => $error !== '']) }}>
    {{ $append ?? '' }}
    @if ($grouped)</div>@endif
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
