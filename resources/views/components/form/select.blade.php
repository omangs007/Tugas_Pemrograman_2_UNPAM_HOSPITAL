@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'multiple' => false,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = '';
    if (isset($errors)) {
        $error = (string) ($errors->first($key) ?: ($multiple ? $errors->first($key.'.*') : ''));
    }
    $options = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;
    $chosen = array_map(
        fn ($item) => (string) ($item instanceof \BackedEnum ? $item->value : $item),
        \Illuminate\Support\Arr::wrap(old($key, $selected)),
    );
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}@if ($multiple)[]@endif"@if ($multiple) multiple @endif @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-select', 'is-invalid' => $error !== '']) }}>
        @if ($placeholder !== null && ! $multiple)
            <option value="" disabled @selected($chosen === [] || $chosen === [''])>{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, $chosen, true))>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
