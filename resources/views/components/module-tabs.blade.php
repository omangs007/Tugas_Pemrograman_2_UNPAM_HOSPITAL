@props(['group'])
@php
    // Pola yang sama dengan sidebar: tab ke route yang belum ada dan tab tanpa izin dibuang (F1-R2).
    $tabs = \App\Support\NavigationMenu::items('modules.'.$group, auth()->user());
@endphp
@if (count($tabs) > 0)
    <nav aria-label="Tab modul">
        <ul class="nav nav-tabs mb-3">
            @foreach ($tabs as $tab)
                <li class="nav-item">
                    <a @class(['nav-link', 'active' => $tab['active']]) href="{{ $tab['url'] }}"@if ($tab['active']) aria-current="page"@endif>{{ $tab['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
