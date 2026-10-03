@props(['title' => 'Periksa kembali isian Anda'])
@if (isset($errors) && $errors->any())
    <div {{ $attributes->merge(['class' => 'alert alert-danger', 'role' => 'alert', 'tabindex' => '-1']) }} data-errors-summary>
        <h2 class="h6 alert-heading">{{ $title }}</h2>
        <ul class="mb-0">
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $message)
                    <li><a href="#{{ trim(str_replace(['.', '[', ']'], ['-', '-', ''], $field), '-') }}" class="alert-link">{{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
