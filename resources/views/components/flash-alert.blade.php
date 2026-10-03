@props(['keys' => ['success', 'error', 'warning']])
@php
    $variants = [
        'success' => ['class' => 'alert-success', 'role' => 'status', 'icon' => 'bi-check-circle-fill'],
        'error' => ['class' => 'alert-danger', 'role' => 'alert', 'icon' => 'bi-x-octagon-fill'],
        'warning' => ['class' => 'alert-warning', 'role' => 'alert', 'icon' => 'bi-exclamation-triangle-fill'],
    ];
@endphp
@foreach ($keys as $key)
    @if (isset($variants[$key]) && is_string($message = session($key)) && $message !== '')
        <div class="alert {{ $variants[$key]['class'] }} d-flex align-items-start gap-2 fade show" role="{{ $variants[$key]['role'] }}" data-flash="{{ $key }}">
            <i class="bi {{ $variants[$key]['icon'] }} flex-shrink-0" aria-hidden="true"></i>
            <div class="flex-grow-1">{{ $message }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
@endforeach
