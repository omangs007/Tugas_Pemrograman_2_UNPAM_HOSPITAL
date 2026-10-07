@php
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $breadcrumbItems = [
        ['label' => 'Pesan Kontak'],
    ];
@endphp
<x-layouts.admin title="Pesan Kontak">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-data-table
        :rows="$contactMessages"
        search-placeholder="Cari nama, email, atau subjek…"
        caption="Daftar pesan dari formulir kontak"
        empty-title="Belum ada pesan"
        empty-message="Pesan dari formulir kontak akan tampil di sini.">
        <x-slot:filters>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Http\Requests\Admin\ContactMessageIndexRequest::STATUS_MAP as $key => $status)
                        <option value="{{ $key }}" @selected($statusNow === $key)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        <x-slot:head>
            <th scope="col">Status</th>
            <th scope="col">Pengirim</th>
            <th scope="col">Subjek</th>
            <th scope="col">Diterima</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($contactMessages as $item)
            <tr @class(['fw-bold' => $item->status === \App\Enums\ContactMessageStatus::New])>
                <td data-label="Status"><x-status-badge :status="$item->status" /></td>
                <td data-label="Pengirim">
                    <span>{{ $item->name }}</span>
                    <div class="small text-secondary fw-normal">{{ $item->email }}</div>
                </td>
                <td data-label="Subjek">{{ $item->subject }}</td>
                <td data-label="Diterima">
                    <time datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->translatedFormat('j M Y, H.i') }}</time>
                </td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.messages.show', $item) }}" class="btn btn-sm btn-outline-primary" aria-label="Buka pesan {{ $item->subject }}">Buka</a>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
