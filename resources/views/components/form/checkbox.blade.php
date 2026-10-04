@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'value' => 1,
    'checked' => false,
    'withHidden' => false,
    'checkedValues' => [],
    'showError' => true,
])
@php
    // Nama larik ("specialty_ids[]"): satu kelompok kotak dengan id unik per nilai (F2-R26).
    $isArray = str_ends_with($name, '[]');
    $field = $isArray ? substr($name, 0, -2) : $name;
    $groupId = trim(str_replace(['.', '[', ']'], ['-', '-', ''], $field), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $field), '.');
    $id = $id ?: ($isArray ? $groupId.'-'.trim((string) preg_replace('/[^A-Za-z0-9_]+/', '-', (string) $value), '-') : $groupId);

    $error = '';
    if (isset($errors)) {
        $error = (string) $errors->first($key);
        if ($error === '' && $isArray) {
            $error = (string) $errors->first($key.'.*');
        }
    }
    $errorId = $isArray ? $groupId.'-error' : $id.'-error';
    $renderError = $error !== '' && (! $isArray || $showError);

    $hasOldInput = request()->hasSession() && request()->session()->hasOldInput();
    if ($isArray) {
        $selected = $hasOldInput ? old($key, []) : $checkedValues;
        $selected = collect($selected)->filter(fn ($item) => is_scalar($item))->map(fn ($item) => (string) $item)->all();
        $isChecked = in_array((string) $value, $selected, true) || (! $hasOldInput && (bool) $checked);
    } else {
        $isChecked = $hasOldInput
            ? (string) old($key, $withHidden ? '0' : null) === (string) $value
            : (bool) $checked;
    }
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' && ($renderError || $isArray) ? $errorId : ''));
@endphp
<div class="mb-3">
    @if ($withHidden && ! $isArray)<input type="hidden" name="{{ $name }}" value="0">@endif
    <div class="form-check">
        <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked($isChecked) @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-check-input', 'is-invalid' => $error !== '']) }}>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    </div>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($renderError)<div id="{{ $errorId }}" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
