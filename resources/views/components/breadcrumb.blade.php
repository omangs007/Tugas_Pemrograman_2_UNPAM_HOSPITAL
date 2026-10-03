@props(['items' => [], 'home' => 'home'])
@php
    $isAdmin = $home === 'admin.dashboard';
    $homeUrl = \Illuminate\Support\Facades\Route::has($home) ? route($home) : url($isAdmin ? '/admin' : '/');
    $trail = array_merge([['label' => $isAdmin ? 'Dasbor' : 'Beranda', 'url' => $homeUrl]], array_values($items));
    $last = count($trail) - 1;
@endphp
<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="breadcrumb">
        @foreach ($trail as $index => $item)
            @if ($index === $last)
                <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
            @elseif (! empty($item['url']))
                <li class="breadcrumb-item"><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
            @else
                <li class="breadcrumb-item">{{ $item['label'] }}</li>
            @endif
        @endforeach
    </ol>
</nav>
