@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'value' => 1,
    'checked' => false,
    'withHidden' => false,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $hasOldInput = request()->hasSession() && request()->session()->hasOldInput();
    $isChecked = $hasOldInput
        ? (string) old($key, $withHidden ? '0' : null) === (string) $value
        : (bool) $checked;
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    @if ($withHidden)<input type="hidden" name="{{ $name }}" value="0">@endif
    <div class="form-check">
        <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked($isChecked) @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-check-input', 'is-invalid' => $error !== '']) }}>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    </div>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
