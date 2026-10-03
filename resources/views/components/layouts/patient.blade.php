@props(['title'])
@php
    $account = auth()->user();
    $portal = \App\Support\NavigationMenu::items('patient', $account);
    $logoutUrl = ($account instanceof \App\Models\User && Route::has('logout')) ? route('logout') : null;
    $hasSidebar = $portal !== [] || $logoutUrl !== null;
@endphp
<x-layouts.public :title="$title" :noindex="true">
    <x-slot:breadcrumb>{{ $breadcrumb ?? '' }}</x-slot:breadcrumb>

    <div class="row g-4">
        @if ($hasSidebar)
            <aside class="col-lg-3">
                <nav aria-label="Menu portal pasien">
                    <ul class="nav nav-pills flex-nowrap flex-lg-column overflow-auto gap-1 pb-2">
                        @foreach ($portal as $item)
                            <li class="nav-item">
                                <a @class([
                                    'nav-link d-flex align-items-center gap-2',
                                    'active' => $item['active'],
                                    'btn btn-primary text-white' => ($item['emphasis'] ?? false) && ! $item['active'],
                                ]) href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>
                                    @if (! empty($item['icon']))
                                        <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                    @endif
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                        @if ($logoutUrl)
                            <li class="nav-item">
                                <form method="POST" action="{{ $logoutUrl }}">
                                    @csrf
                                    <button type="submit" class="nav-link d-flex align-items-center gap-2 w-100 border-0 bg-transparent text-start">
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                        <span>Keluar</span>
                                    </button>
                                </form>
                            </li>
                        @endif
                    </ul>
                </nav>
            </aside>
        @endif

        <div @class(['col-lg-9' => $hasSidebar, 'col-12' => ! $hasSidebar])>
            {{ $slot }}
        </div>
    </div>
</x-layouts.public>
