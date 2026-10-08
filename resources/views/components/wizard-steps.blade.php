@props(['current' => 1, 'links' => []])
@php
    $labels = ['Poliklinik', 'Dokter', 'Tanggal & Sesi', 'Data Pendaftaran', 'Konfirmasi'];
    $total = count($labels);
    $current = max(1, min($total, (int) $current));
@endphp
<nav aria-label="Langkah pendaftaran" {{ $attributes->class(['wizard-steps', 'mb-4']) }}>
    <p class="d-sm-none fw-semibold mb-0" data-wizard-summary>Langkah {{ $current }} dari {{ $total }}: {{ $labels[$current - 1] }}</p>
    <ol class="d-none d-sm-flex flex-wrap gap-3 list-unstyled mb-0">
        @foreach ($labels as $index => $label)
            @php
                $number = $index + 1;
                $isCurrent = $number === $current;
                $isDone = $number < $current;
                $url = $isDone ? ($links[$number] ?? null) : null;
            @endphp
            <li @class(['d-flex align-items-center gap-2', 'fw-bold' => $isCurrent, 'text-body-secondary' => ! $isCurrent && ! $isDone]) @if ($isCurrent) aria-current="step" @endif>
                <span @class(['badge rounded-pill', 'text-bg-primary' => $isCurrent, 'text-bg-success' => $isDone, 'text-bg-light border' => ! $isCurrent && ! $isDone])>
                    @if ($isDone)
                        <i class="bi bi-check-lg" aria-hidden="true"></i>
                        <span class="visually-hidden">Langkah selesai: </span>
                    @endif
                    {{ $number }}
                </span>
                @if ($url)
                    <a href="{{ $url }}">{{ $label }}</a>
                @else
                    <span>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
