@props([
    'name',
    'legend' => null,
    'label' => null,
    'id' => null,
    'help' => null,
    'required' => false,
    'options' => [],
    'selected' => null,
    'inline' => false,
])
@php
    $legendText = $legend ?? $label;

    if ($legendText === null) {
        throw new \InvalidArgumentException('x-form.radio-group membutuhkan prop legend.');
    }

    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $options = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;
    $current = old($key, $selected);
    $chosen = is_scalar($current) ? (string) $current : '';
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
@endphp
<fieldset id="{{ $id }}" role="radiogroup" tabindex="-1"@if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['mb-3']) }}>
    <legend class="form-label fw-semibold fs-6">{{ $legendText }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</legend>
    @foreach ($options as $optionValue => $optionLabel)
        @php
            $optionId = $id.'-'.preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $optionValue);
        @endphp
        <div class="form-check{{ $inline ? ' form-check-inline' : '' }}">
            <input type="radio" id="{{ $optionId }}" name="{{ $name }}" value="{{ $optionValue }}" @checked($chosen !== '' && $chosen === (string) $optionValue) class="form-check-input{{ $error !== '' ? ' is-invalid' : '' }}">
            <label class="form-check-label" for="{{ $optionId }}">{{ $optionLabel }}</label>
        </div>
    @endforeach
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</fieldset>
